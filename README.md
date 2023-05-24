# A simple API syntax for Symfony controllers

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

## Testing

Integrate the tests into your current PhpUnit project. Adding this to your phpunit.xml file : 

    <testsuites>
        <testsuite name="Api Test Suite">
            <directory>vendor/symfony-api/tests</directory>
        </testsuite>
    </testsuites>