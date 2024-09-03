<?php

namespace Wegmeister\LanguageRedirect\Middleware;

use Exception;
use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\I18n\Detector;
use Neos\Flow\I18n\Locale;
use Neos\Neos\Domain\Service\ContentDimensionPresetSourceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LanguageRedirectMiddleware implements MiddlewareInterface
{
    #[Flow\Inject]
    protected Detector $localeDetector;

    #[Flow\Inject]
    protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource;

    #[Flow\InjectConfiguration(path: 'languageCodeOverrides')]
    protected array $languageCodeOverrides;

    #[Flow\InjectConfiguration(path: 'feLanguageCookieName')]
    protected string $feLanguageCookieName;

    /**
     * Redirect all requests to the homepage without a language prefix to the
     * homepage with the language that matches the browser language best.
     * If no matching language is found, the fallback language is used.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $next
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            // We will only handle GET requests.
            // Therefore we simply pass the request to the next request handler.
            return $next->handle($request);
        }

        $uri = $request->getUri();

        if (!empty(trim($uri->getPath(), " \t\n\r\0\x0B/"))) {
            // We will only handle language detection on the root page.
            // Therefore we simply pass the request to the next request handler.
            return $next->handle($request);
        }

        $defaultPreset = $this->contentDimensionPresetSource->getDefaultPreset('language');

        $preset = null;

        if ($this->feLanguageCookieName && isset($request->getCookieParams()[$this->feLanguageCookieName])) {
            $feLanguageCookie = $request->getCookieParams()[$this->feLanguageCookieName];
            $preset = $this->findMatchingPresetByFeLanguageCookie($feLanguageCookie);
        }

        if ($preset == null) {
            $preset = $this->findMatchingPresetByAcceptLanguageHeader(
                $request->getHeader('Accept-Language')[0] ?? '',
            );
        }

        if ($preset == null) {
            $preset = $defaultPreset;
        }

        if ($preset === null) {
            throw new Exception(
                'Unable to find a language preset for the detected locale '
                . 'and no default preset is configured. '
                . 'Check your content dimensions settings: '
                . 'Neos.ContentRepository.contentDimensions.language.',
                1701173151780
            );
        }

        $uri = $uri->withPath('/' . $preset['uriSegment']);

        return new Response(307, [
            'Location' => (string)$uri,
        ]);
    }

    /**
     * Match user languages against given language dimensions.
     * Return the Locale, that fits best.
     *
     * @param string $acceptLanguageHeader
     *
     * @return array|null
     */
    protected function findMatchingPresetByAcceptLanguageHeader(string $acceptLanguageHeader): ?array
    {
        $parts = explode(',', $acceptLanguageHeader);
        while ($acceptLanguageHeader !== '') {
            $detectedLocale = $this->localeDetector->detectLocaleFromHttpHeader(
                $acceptLanguageHeader
            );

            if (!$detectedLocale instanceof Locale) {
                // No Locale found, continue with next part
                array_shift($parts);
                $acceptLanguageHeader = implode(',', $parts);
                continue;
            }

            $languageCode = $detectedLocale->getLanguage();
            if (isset($this->languageCodeOverrides[$languageCode])) {
                // If there is a language code override, use it
                $languageCode = $this->languageCodeOverrides[$languageCode];
            }

            $preset = $this->contentDimensionPresetSource->findPresetByUriSegment(
                'language',
                $languageCode
            );

            if ($preset !== null) {
                return $preset;
            }

            // No preset for the detected locale found, continue with next part
            array_shift($parts);
            $acceptLanguageHeader = implode(',', $parts);
        }

        return null;
    }

    /**
     * Match frontend language cookie against given language dimensions.
     * Return the Locale, that fits best.
     *
     * @param string $acceptLanguageHeader
     *
     * @return array|null
     */
    protected function findMatchingPresetByFeLanguageCookie(string $feLanguageCookie): ?array
    {
        $detectedLocale = $this->localeDetector->detectLocaleFromLocaleTag($feLanguageCookie);

        if (!$detectedLocale instanceof Locale) {
            // No Locale found
            return null;
        }

        $languageCode = $detectedLocale->getLanguage();
        if (isset($this->languageCodeOverrides[$languageCode])) {
            // If there is a language code override, use it
            $languageCode = $this->languageCodeOverrides[$languageCode];
        }

        $preset = $this->contentDimensionPresetSource->findPresetByUriSegment(
            'language',
            $languageCode
        );

        if ($preset !== null) {
            return $preset;
        }

        return null;
    }
}
