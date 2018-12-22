<?php

declare(strict_types=1);

namespace Chiron\Http\Parser;

use Chiron\Http\Exception\Client\BadRequestHttpException;
use Psr\Http\Message\ServerRequestInterface;
use function array_shift;
use function explode;
use function preg_match;
use function trim;

class XmlParser implements RequestParserInterface
{
    /**
     * @var bool whether to throw a [[BadRequestHttpException]] if the body is invalid
     */
    public $throwException = false;

    public function supports(string $contentType): bool
    {
        $parts = explode(';', $contentType);
        $mime = trim(array_shift($parts));

        return (bool) preg_match('~application/([a-z.]+\+)?xml~', $mime) || $mime === 'text/xml';
        //return (bool) preg_match('#[/+]xml$#', $mediaType);

        // Regex for : 'application/xml' or 'application/*+xml' or 'text/xml'
        //if (preg_match('~^application/([a-z.]+\+)?xml($|;)~', $mediaType) || $mediaType === 'text/xml') {
        //if (preg_match('~^application/([a-z.]+\+)?xml~', $mediaType) || preg_match('~^text/xml~', $mediaType)) {
        //if (preg_match('~(application|text)/([a-z.]+\+)?xml~', $mediaType)) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws MalformedRequestBodyException
     */
    public function parse(ServerRequestInterface $request): ServerRequestInterface
    {
        $rawBody = (string) $request->getBody();
        // disable entity loading to prevent XXE (XML External Entity attacks) attacks
        $backup = libxml_disable_entity_loader(true);
        $backup_errors = libxml_use_internal_errors(true);
        // parse XML and disable internet connection when parsing XML
        //$parsed = simplexml_load_string($body);
        // TODO : regarder un autre exemple ici : https://github.com/yiisoft/yii2-httpclient/blob/master/src/XmlParser.php
        $parsedBody = simplexml_load_string($rawBody, 'SimpleXMLElement', LIBXML_NONET);
        // restore lib settings
        libxml_disable_entity_loader($backup);
        libxml_clear_errors();
        libxml_use_internal_errors($backup_errors);

        // TODO : on devrait pas ajouter un "if (! empty($rawBody) && $parsedBody === false)" ??? comme c'est fait pour le JSON ????
        if ($parsedBody === false) {
            $parsedBody = null;

            if ($this->throwException) {
                throw new BadRequestHttpException('Error when parsing XML request body');
            }
        }

        return $request->withParsedBody($parsedBody);
    }
}
