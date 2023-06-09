<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\AbstractQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\EveryQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

readonly class ApiEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private ParameterBagInterface $parameterBag
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'apiControllerArgumentValidate',
            KernelEvents::EXCEPTION => 'onKernelException',
            KernelEvents::VIEW => 'onKernelView',
        ];
    }

    public function onKernelView($event): void
    {
        $response = $event->getControllerResult();

        if (!$response instanceof ApiResponse) {
            return;
        }

        if (is_null($response->getPrettyPrint())) {
            $response->setPrettyPrint($this->parameterBag->get('api_pretty_print'));
        }

        $event->setResponse(
            $response->toJsonResponse()
        );
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!RequestHelper::requestIsOnSubClassOf(
            $request,
            AbstractApiController::class
        )) {
            return;
        }

        $exception = $event->getThrowable();
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
        }

        $event->setResponse(
            AbstractApiController::apiResponseError(
                $exception->getMessage(),
                prettyPrint: $this->parameterBag->get('api_pretty_print'),
                status: $status
            )->toJsonResponse(),
        );
    }

    public function apiControllerArgumentValidate(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $controllerData = $event->getController();

        if (!is_array($controllerData) || !is_subclass_of($controllerData[0], AbstractApiController::class)) {
            return;
        }

        $requestedController = $controllerData[0]::class.ClassHelper::METHOD_SEPARATOR.$controllerData[1];

        $apiQueryAttributes = ClassHelper::getChildrenAttributes(
            $requestedController,
            QueryOptionTrait::class
        );

        $optionsAttributes = [];
        $queryParameters = $request->query->all();

        foreach ($apiQueryAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $optionsAttributes[$instance->key] = $instance;
        }

        foreach ($queryParameters as $key => $value) {
            if (!isset($optionsAttributes[$key])) {
                if (isset($optionsAttributes[EveryQueryOption::KEY])) {
                    continue;
                }

                $this->createError($event,
                    'Unknown given query option '
                    .$key.'. '
                    .(!empty($optionsAttributes)
                        ? 'Allowed query options are : '.implode(', ', array_keys($optionsAttributes)).'.'
                        : 'No query option allowed.'
                    ), ['got' => $key.RequestHelper::URL_QUERY_STRING_EQUAL.$value]);

                return;
            }

            /** @var AbstractQueryOption $queryOption */
            $queryOption = $optionsAttributes[$key];
            $constraint = $queryOption->getConstraint();

            // Parse value if constraint is on type.
            if ($constraint instanceof Type) {
                $value = RequestHelper::parseRequestValue(
                    $value,
                    $constraint->type
                );

                // Replace by parsed value.
                $queryParameters[$key] = $value;
            }

            $violations = $this->validator->validate($value, $constraint);

            if ($violations->count()) {
                $this->createError(
                    $event,
                    'Query option **'.$key.'** does not match constraints.',
                    [
                        'got' => $value,
                        'type' => $constraint::class,
                    ]
                );

                return;
            }
        }

        // Check if required attributes are present in query strings
        /**
         * @var string                             $key
         * @var QueryOptionConstrainedTrait::class $attribute
         */
        foreach ($optionsAttributes as $key => $attribute) {
            // Not sent.
            if (ClassHelper::classUsesTrait($attribute, QueryOptionConstrainedTrait::class)
                && !array_key_exists($key, $queryParameters)
            ) {
                if (true === $attribute->required) {
                    $this->createError($event, 'Required query option **'.$key.'** is missing.', ['required' => $key]);

                    return;
                } else {
                    // Replace by default.
                    $queryParameters[$key] = $attribute->default;
                }
            }
        }

        $request->query->replace($queryParameters);
    }

    private function createError(
        ControllerArgumentsEvent $event,
        string $errorMessage,
        array $errorData
    ): void {
        $event->setController(
            function() use
            (
                $errorMessage,
                $errorData
            ) {
                return AbstractApiController::apiResponseError(
                    $errorMessage,
                    [VariableHelper::DATA => $errorData]
                );
            }
        );
    }
}
