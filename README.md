# Webigniter CMS Laravel client

This package integrates the Webigniter CMS services into your Laravel applications. This package requires a working Laravel installation.
This readme provides an overview of integrating and using the Webigniter Laravel Client in your Laravel projects. For more details, visit the [Webigniter Documentation](https://webigniter.net/documentation).

## Installation

Install the Webigniter Laravel client via Composer:
```bash
composer require webigniter-net/laravel-client
```

## Get a free license Key

To generate a free license key, create an account at [Webigniter.net](https://webigniter.net/create-account). Then login to the CMS and head to 'License' and click 'show/hide license key'. For more information about the license key, visit the documentation page about [finding your license key](https://webigniter.net/documentation/getting-started/find-your-license-key)

## Setting Up License Key

Add your license key to your `.env` file:

```bash
WEBIGNITER_KEY=YOURKEY
```

## Laravel Blade Integration

Once installed and after setting up the license key, you can integrate pages from the Webigniter CMS into your Laravel Blade templates. This integration allows you to display content managed within the Webigniter CMS directly within your Laravel project's views. For a detailed description on creating your first page, visit our documentation page about [creating your first page](https://webigniter.net/documentation/getting-started/create-your-first-page)

## Example Blade Layout

Here's an example of integrating Webigniter services into your Blade layout:

```html
<html>
<head>
    <title>My awesome Webigniter page</title>
</head>
<body>
    <?php $webigniter->getSectionsContent(); ?>
</body>
</html>
```

## Example Section File

Here's an example of a section file using Blade syntax, where 'title' corresponds to an element handle in your CMS:

```html
<h1>{{ $title }}</h1>
```