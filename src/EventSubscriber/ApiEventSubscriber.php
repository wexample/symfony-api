<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\AbstractQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\EveryQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\DataHelper;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ApiEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'apiControllerArgumentValidate',
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION => 'onKernelException',
            KernelEvents::VIEW => 'onKernelView',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        $attributes = $this->getControllerAttributes(
            $event,
            ValidateRequestContent::class
        );

        if (is_null($attributes) or empty($attributes)) {
            return;
        }

        $request = $event->getRequest();

        $contentString = $request->getContent();
        $content = json_decode(
            $request->getContent(),
            associative: true
        );

        if (!$content) {
            return;
        }

        foreach ($attributes as $attribute) {
            /** @var ValidateRequestContent $instance */
            $instance = $attribute->newInstance();
            /** @var AbstractDto $dtoClassType */
            $dtoClassType = $instance->dto;

            // First validate input data.
            $errors = $this->validator->validate(
                $content,
                $dtoClassType::getConstraints()
            );

            if (count($errors) > 0) {
                $this->createError(
                    $event,
                    (string) $errors
                );

                return;
            }

            try {
                // Constraints passed, now we create the actual dto.
                $dto = $this->serializer->deserialize(
                    $contentString,
                    $dtoClassType,
                    DataHelper::FORMAT_JSON
                );

                $request->attributes->set(
                    $instance->attributeName,
                    $dto
                );

            } catch (\Exception $e) {
                // Some errors can remain on deserialization.
                $this->createError(
                    $event,
                    $e->getMessage()
                );

                return;
            }
        }
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
        $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        if ($exception instanceof HttpExceptionInterface) {
            $code = $exception->getStatusCode();
        }

        $data = null;
        $message = $exception->getMessage();
        if ($this->parameterBag->get('api.debug') ?? false) {
            $data = [
                'trace' => $exception->getTrace(),
            ];
        }

        $event->setResponse(
            AbstractApiController::apiResponseError(
                message: $message,
                data: $data,
                prettyPrint: $this->parameterBag->get('api_pretty_print'),
                code: $code
            )->toJsonResponse(),
        );
    }

    protected function getControllerAttributes(
        KernelEvent $event,
        string $attributeClass
    ): ?array {
        $controllerData = $event->getController();

        if (!is_array($controllerData) || !is_subclass_of($controllerData[0], AbstractApiController::class)) {
            return null;
        }

        $requestedController = $controllerData[0]::class.ClassHelper::METHOD_SEPARATOR.$controllerData[1];

        return ClassHelper::getChildrenAttributes(
            $requestedController,
            $attributeClass
        );
    }

    public function apiControllerArgumentValidate(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $optionsAttributes = [];
        $queryParameters = $request->query->all();
        $apiQueryAttributes = $this->getControllerAttributes(
            $event,
            QueryOptionTrait::class
        );

        if (is_null($apiQueryAttributes) or empty($apiQueryAttributes)) {
            return;
        }

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
            $queryParameters[$key] = $value = $queryOption->parseValue($value);

            $constraint = $queryOption->getConstraint();
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
        KernelEvent $event,
        string $errorMessage,
        array $errorData = []
    ): void {
        $event->setController(
            fn() => AbstractApiController::apiResponseError(
                $errorMessage,
                [VariableHelper::DATA => $errorData]
            )
        );
    }
}
