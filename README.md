# A simple API syntax for Symfony controllers

![Pipeline status](https://gitlab.wexample.com/wexample/symfony-api/badges/main/pipeline.svg)

## Install

- Note that the src/Api/Controller directory will be accessible as service.

### Create API Controller directory

Create a new directory in `src/Api/Controller/`.

### Update routing.yaml

Add the folder to your routes.yaml for loading routes.

    api_controllers:
        resource: '../src/Api/Controller/'
        type: annotation

## Usage

Create a controller extending the AbstractApiController class.

## Testing in your project

Testing api vitals in your own project can help to check that every vital of your site is working well.
Package test folder should be made accessible to composer autoloader.

### Add to Composer

In composer.json

    "autoload-dev": {
        "psr-4": {
            "Wexample\\SymfonyApi\\Tests\\": "vendor/wexample/symfony-api/tests/"
        }
    },

### Add to PhpUnit

In phpunit.xml.dist

    <testsuites>
        <testsuite name="Api Test Suite">
            <directory>vendor/symfony-api/tests</directory>
        </testsuite>
    </testsuites>

