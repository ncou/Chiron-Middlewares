<?php

declare(strict_types=1);

namespace Chiron\Http\Parser;

use Chiron\Http\Psr\Stream;
use Chiron\Http\Psr\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;

// https://github.com/reactphp/http/blob/master/src/Io/MultipartParser.php

//https://github.com/yiisoft/yii2/blob/master/framework/web/MultipartFormDataParser.php

// TODO : finir de mettre le typehint sur chaque fonction pour connaitre le type de paramétre à utiliser !!!!!!!!!

/**
 * [Internal] Parses a string body with "Content-Type: multipart/form-data" into structured data.
 *
 * This is used internally to parse incoming request bodies into structured data
 * that resembles PHP's `$_POST` and `$_FILES` superglobals.
 *
 * @internal
 *
 * @see https://tools.ietf.org/html/rfc7578
 * @see https://tools.ietf.org/html/rfc2046#section-5.1.1
 */
// TODO : renommer en MultipartFormDataParser.php + nettoyer le code qui fait référence à du PHP 5
class FormDataParser implements RequestParserInterface
{
    /**
     * @var ServerRequestInterface|null
     */
    private $request;

    /**
     * @var int|null
     */
    private $maxFileSize;

    /**
     * ini setting "max_input_vars".
     *
     * Does not exist in PHP < 5.3.9 or HHVM, so assume PHP's default 1000 here.
     *
     * @var int
     *
     * @see http://php.net/manual/en/info.configuration.php#ini.max-input-vars
     */
    private $maxInputVars = 1000;

    /**
     * ini setting "max_input_nesting_level".
     *
     * Does not exist in HHVM, but assumes hard coded to 64 (PHP's default).
     *
     * @var int
     *
     * @see http://php.net/manual/en/info.configuration.php#ini.max-input-nesting-level
     */
    private $maxInputNestingLevel = 64;

    /**
     * ini setting "upload_max_filesize".
     *
     * @var int
     */
    private $uploadMaxFilesize;

    /**
     * ini setting "max_file_uploads".
     *
     * Additionally, setting "file_uploads = off" effectively sets this to zero.
     *
     * @var int
     */
    private $maxFileUploads;

    private $postCount = 0;

    private $filesCount = 0;

    private $emptyCount = 0;

    /**
     * @param int|string|null $uploadMaxFilesize
     * @param int|null        $maxFileUploads
     */
    // TODO : virer ces paramétre du constructeur, on doit uniquement se baser sur les infos présentes dans le fichier ini de PHP
    public function __construct($uploadMaxFilesize = null, $maxFileUploads = null)
    {
        $var = ini_get('max_input_vars');
        if ($var !== false) {
            $this->maxInputVars = (int) $var;
        }
        $var = ini_get('max_input_nesting_level');
        if ($var !== false) {
            $this->maxInputNestingLevel = (int) $var;
        }

        if ($uploadMaxFilesize === null) {
            $uploadMaxFilesize = ini_get('upload_max_filesize');
        }

        $this->uploadMaxFilesize = $this->iniSizeToBytes((string) $uploadMaxFilesize);
        $this->maxFileUploads = $maxFileUploads === null ? (ini_get('file_uploads') === '' ? 0 : (int) ini_get('max_file_uploads')) : (int) $maxFileUploads;
    }

    public function supports(string $contentType): bool
    {
        return (bool) preg_match('#^multipart/form-data($|[ ;])#', $contentType);
    }

    public function parse(ServerRequestInterface $request): ServerRequestInterface
    {
        $contentType = $request->getHeaderLine('content-type');
        if (! preg_match('/boundary="?(.*)"?$/', $contentType, $matches)) {
            return $request;
        }

        $this->request = $request;
        $this->parseBody('--' . $matches[1], (string) $request->getBody());

        $request = $this->request;
        $this->request = null;
        $this->postCount = 0;
        $this->filesCount = 0;
        $this->emptyCount = 0;
        $this->maxFileSize = null;

        return $request;
    }

    private function parseBody(string $boundary, string $buffer)
    {
        $len = strlen($boundary);

        // ignore everything before initial boundary (SHOULD be empty)
        $start = strpos($buffer, $boundary . "\r\n");

        while ($start !== false) {
            // search following boundary (preceded by newline)
            // ignore last if not followed by boundary (SHOULD end with "--")
            $start += $len + 2;
            $end = strpos($buffer, "\r\n" . $boundary, $start);
            if ($end === false) {
                break;
            }

            // parse one part and continue searching for next
            $this->parsePart(substr($buffer, $start, $end - $start));
            $start = $end;
        }
    }

    private function parsePart(string $chunk)
    {
        $pos = strpos($chunk, "\r\n\r\n");
        if ($pos === false) {
            return;
        }

        // Separate part headers from part body
        $headers = $this->parseHeaders((string) substr($chunk, 0, $pos));
        $body = (string) substr($chunk, $pos + 4);

        if (! isset($headers['content-disposition'])) {
            return;
        }

        $name = $this->getParameterFromHeader($headers['content-disposition'], 'name');
        if ($name === null) {
            return;
        }

        $filename = $this->getParameterFromHeader($headers['content-disposition'], 'filename');
        // filename could be an empty string '' if there is no file, but this case is handled after in the code
        if ($filename !== null) {
            $this->parseFile(
                $name,
                $filename,
                isset($headers['content-type'][0]) ? $headers['content-type'][0] : null,
                $body
            );
        } else {
            $this->parsePost($name, $body);
        }
    }

    private function parseFile($name, $filename, $contentType, $contents)
    {
        $file = $this->parseUploadedFile($filename, $contentType, $contents);
        if ($file === null) {
            return;
        }

        $this->request = $this->request->withUploadedFiles($this->extractPost(
            $this->request->getUploadedFiles(),
            $name,
            $file
        ));
    }

    private function parseUploadedFile($filename, $contentType, $contents)
    {
        $size = strlen($contents);

        // no file selected (zero size and empty filename)
        if ($size === 0 && $filename === '') {
            // ignore excessive number of empty file uploads
            if (++$this->emptyCount + $this->filesCount > $this->maxInputVars) {
                return;
            }

            return new UploadedFile(
                new Stream(fopen('php://temp', 'r')),
                $size,
                UPLOAD_ERR_NO_FILE,
                $filename,
                $contentType
            );
        }

        // ignore excessive number of file uploads
        if (++$this->filesCount > $this->maxFileUploads) {
            return;
        }

        // file exceeds "upload_max_filesize" ini setting
        if ($size > $this->uploadMaxFilesize) {
            return new UploadedFile(
                new Stream(fopen('php://temp', 'r')),
                $size,
                UPLOAD_ERR_INI_SIZE,
                $filename,
                $contentType
            );
        }

        // file exceeds MAX_FILE_SIZE value
        if ($this->maxFileSize !== null && $size > $this->maxFileSize) {
            return new UploadedFile(
                new Stream(fopen('php://temp', 'r')),
                $size,
                UPLOAD_ERR_FORM_SIZE,
                $filename,
                $contentType
            );
        }

        // create a tempory stream and write the uploaded file inside
        $stream = new Stream(fopen('php://temp', 'wb+'));
        $stream->write($contents);
        $stream->rewind();

        return new UploadedFile(
            $stream,
            $size,
            UPLOAD_ERR_OK,
            $filename,
            $contentType
        );
    }

    private function parsePost($name, $value)
    {
        // ignore excessive number of post fields
        if (++$this->postCount > $this->maxInputVars) {
            return;
        }

        $this->request = $this->request->withParsedBody($this->extractPost(
            $this->request->getParsedBody(),
            $name,
            $value
        ));

        //handle the special case with the hidden 'MAX_FILE_SIZE' field : http://php.net/manual/en/features.file-upload.post-method.php
        if (strtoupper($name) === 'MAX_FILE_SIZE') {
            $this->maxFileSize = (int) $value;

            if ($this->maxFileSize === 0) {
                $this->maxFileSize = null;
            }
        }
    }

    private function parseHeaders($header)
    {
        $headers = [];

        foreach (explode("\r\n", trim($header)) as $line) {
            $parts = explode(':', $line, 2);
            if (! isset($parts[1])) {
                continue;
            }

            $key = strtolower(trim($parts[0]));
            $values = explode(';', $parts[1]);
            $values = array_map('trim', $values);
            $headers[$key] = $values;
        }

        return $headers;
    }

    private function getParameterFromHeader(array $header, $parameter)
    {
        foreach ($header as $part) {
            if (preg_match('/' . $parameter . '="?(.*)"$/', $part, $matches)) {
                return $matches[1];
            }
        }
    }

    private function extractPost($postFields, $key, $value)
    {
        $chunks = explode('[', $key);
        if (count($chunks) == 1) {
            $postFields[$key] = $value;

            return $postFields;
        }

        // ignore this key if maximum nesting level is exceeded
        if (isset($chunks[$this->maxInputNestingLevel])) {
            return $postFields;
        }

        $chunkKey = rtrim($chunks[0], ']');
        $parent = &$postFields;
        for ($i = 1; isset($chunks[$i]); $i++) {
            $previousChunkKey = $chunkKey;

            if ($previousChunkKey === '') {
                $parent[] = [];
                end($parent);
                $parent = &$parent[key($parent)];
            } else {
                if (! isset($parent[$previousChunkKey]) || ! is_array($parent[$previousChunkKey])) {
                    $parent[$previousChunkKey] = [];
                }
                $parent = &$parent[$previousChunkKey];
            }

            $chunkKey = rtrim($chunks[$i], ']');
        }

        if ($chunkKey === '') {
            $parent[] = $value;
        } else {
            $parent[$chunkKey] = $value;
        }

        return $postFields;
    }

    /**
     * Convert a ini like size to a numeric size in bytes.
     *
     * @param string $size
     *
     * @return int
     */
    // TODO : déplacer cela dans une classe Utils ou alors faire une fonction php globale genre : convert_size_to_byte(string xxx)
    private function iniSizeToBytes(string $size): int
    {
        if (is_numeric($size)) {
            return (int) $size;
        }
        $suffix = strtoupper(substr($size, -1));
        $strippedSize = substr($size, 0, -1);
        if (! is_numeric($strippedSize)) {
            throw new \InvalidArgumentException("$size is not a valid ini size");
        }
        if ($strippedSize <= 0) {
            throw new \InvalidArgumentException("Expect $size to be higher isn't zero or lower");
        }
        if ($suffix === 'K') {
            return $strippedSize * 1024;
        }
        if ($suffix === 'M') {
            return $strippedSize * 1024 * 1024;
        }
        if ($suffix === 'G') {
            return $strippedSize * 1024 * 1024 * 1024;
        }
        if ($suffix === 'T') {
            return $strippedSize * 1024 * 1024 * 1024 * 1024;
        }

        return (int) $size;
    }
}
