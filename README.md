# A simple API syntax for Symfony controllers

## Install

- The src/Api/Controller directory will be accessible as service.

### Update routing.yaml

Add the folder to your routing.yaml for loading routes.

api_controllers:
    resource: '../src/Api/Controller/'
    type: annotation

## Usage

Create a controller extending the AbstractApiController class.