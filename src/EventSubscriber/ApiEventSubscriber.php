<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\AbstractQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

readonly class ApiEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ValidatorInterface $validator
    ) {

    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'apiControllerArgumentValidate',
        ];
    }

    public function apiControllerArgumentValidate(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $controllerData = $event->getController();
        $errorMessage = null;
        $errorData = [];

        if (!is_array($controllerData) || !is_subclass_of($controllerData[0], AbstractApiController::class)) {
            return;
        }

        $requestedController = $controllerData[0]::class.ClassHelper::METHOD_SEPARATOR.$controllerData[1];

        $apiQueryAttributes = ClassHelper::getChildrenAttributes(
            $requestedController,
            QueryOptionTrait::class
        );

        $optionsAttributes = [];

        foreach ($apiQueryAttributes as $attribute) {
            $optionsAttributes[$attribute->newInstance()->key] = $attribute->newInstance();
        }

        foreach ($request->query->all() as $key => $value) {
            if (!$errorMessage) {
                $value = RequestHelper::parseRequestValue($value);

                $errorData = [
                    'got' => $value,
                ];

                if (!isset($optionsAttributes[$key])) {
                    $errorMessage = 'Unknown given query option '
                        .$key.'. '
                        .(!empty($optionsAttributes)
                            ? 'Allowed query options are : '.implode(', ', array_keys($optionsAttributes)).'.'
                            : 'No query option allowed.'
                        );
                } else {
                    /** @var AbstractQueryOption $queryOption */
                    $queryOption = $optionsAttributes[$key];
                    $constraint = $queryOption->getConstraint();
                    $violations = $this->validator->validate(
                        $value,
                        $constraint
                    );

                    if ($violations->count()) {
                        $errorMessage = 'Query option '.$key.' does not match constraints.';

                        $errorData['type'] = $constraint::class;
                    }
                }
            }
        }

        if ($errorMessage) {
            $event->setController(
                function() use
                (
                    $errorMessage,
                    $errorData
                ) {
                    return AbstractApiController::apiResponseError(
                        $errorMessage,
                        [
                            VariableHelper::DATA => $errorData,
                        ]
                    );
                }
            );
        }
    }
}