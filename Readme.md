# Wegmeister.LanguageRedirect

This package provides a simple language detection service for Neos CMS that will redirect users to the language version that fits their Accept-Language header best. It will only redirect if the uri path is empty (e.g. /).

## Installation

Run the following command in your site package:

```bash
composer require --no-update wegmeister/language-redirect
```

Then run `composer update` in your project root.

## Configuration

Sometimes language codes are not configured the same as in Neos. Therefore you can configure a mapping in your `Settings.yaml`:
Also you can configure a feLanguageCookieName to get a cookie value from your frontend in case you want your users last
opened language to reopen the next time he visits your website.

```yaml
Wegmeister:
  LanguageRedirect:
    # Add mappings for language codes if you use some different codes than the default ones.
    languageCodeOverrides:
      # For example, if you use "cz" instead of "cs" for Czech, you can add this mapping:
      cs: cz
      # configure the name of your frontend language cookie
      feLanguageCookieName: _fe_language
```
