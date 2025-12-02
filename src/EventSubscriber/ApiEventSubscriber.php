<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\AbstractQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\EveryQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Service\DtoValidationService;
use Wexample\SymfonyHelpers\Helper\RequestHelper;

class ApiEventSubscriber extends AbstractControllerEventSubscriber
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly DtoValidationService $dtoValidationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION => 'onKernelException',
            KernelEvents::VIEW => 'onKernelView',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $this->validateSentContent($event);
        $this->validateQueryOptions($event);
    }

    protected function createErrorFromMessage(
        KernelEvent $event,
        string $message
    ): void {
        $this->createError(
            $event,
            $message,
            [
                'type' => 'message',
            ]
        );
    }

    protected function createErrorFromViolationList(
        KernelEvent $event,
        string $message,
        ConstraintViolationListInterface $violations
    ): void {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'message' => $violation->getMessage(),
                'type' => 'validation',
                'property' => $violation->getPropertyPath(),
                'code' => $violation->getCode(),
                'value' => $violation->getInvalidValue(),
            ];
        }

        $this->createError(
            $event,
            $message,
            [
                'type' => 'validation_collection',
                'errors' => $errors,
            ]
        );
    }

    public function validateSentContent(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (! is_array($controller)) {
            return;
        }

        $attributes = $this->getControllerClassOrMethodAttributes(
            $event,
            ValidateRequestContent::class
        );

        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();

        foreach ($attributes as $attribute) {
            /** @var ValidateRequestContent $instance */
            $instance = $attribute->newInstance();

            $dto = $this->dtoValidationService->validateDtoFromRequest(
                $request,
                $instance
            );

            if ($dto !== null) {
                $request->attributes->set($instance->attributeName, $dto);
            }
        }
    }

    public function onKernelView($event): void
    {
        $response = $event->getControllerResult();

        if (! $response instanceof ApiResponse) {
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

        if (! RequestHelper::requestIsOnSubClassOf(
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
            $flatTrace = [];

            $flatTrace[] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'class' => get_class($exception),
                'function' => '<throw>',
            ];

            foreach ($exception->getTrace() as $frame) {
                $flatTrace[] = [
                    'file' => $frame['file'] ?? '<internal>',
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            }

            $data = [
                'trace' => $flatTrace,
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

    public function validateQueryOptions(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $optionsAttributes = [];
        $queryParameters = $request->query->all();
        $apiQueryAttributes = $this->getControllerClassOrMethodAttributes(
            $event,
            QueryOptionTrait::class
        );

        if (empty($apiQueryAttributes)) {
            return;
        }

        foreach ($apiQueryAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $optionsAttributes[$instance->key] = $instance;
        }

        foreach ($queryParameters as $key => $value) {
            if (! isset($optionsAttributes[$key])) {
                if (isset($optionsAttributes[EveryQueryOption::KEY])) {
                    continue;
                }

                $this->createErrorFromMessage(
                    $event,
                    'Unknown given query option **' . $key . '**. '
                    . (
                        ! empty($optionsAttributes)
                        ? 'Allowed options are: ' . implode(', ', array_keys($optionsAttributes)) . '.'
                        : 'No query options are allowed.'
                    )
                );

                return;
            }

            /** @var AbstractQueryOption $queryOption */
            $queryOption = $optionsAttributes[$key];
            $queryParameters[$key] = $value = $queryOption->parseValue($value);

            $constraint = $queryOption->getConstraint();
            $violations = $this->validator->validate($value, $constraint);

            if ($violations->count()) {
                $this->createErrorFromViolationList(
                    $event,
                    'Query option **' . $key . '** does not match constraints.',
                    $violations
                );

                return;
            }
        }

        // Check if required attributes are present in query strings
        /**
         * @var string $key
         * @var QueryOptionConstrainedTrait::class $attribute
         */
        foreach ($optionsAttributes as $key => $attribute) {
            // Not sent.
            if (ClassHelper::classUsesTrait($attribute, QueryOptionConstrainedTrait::class)
                && ! array_key_exists($key, $queryParameters)
            ) {
                if (true === $attribute->required) {
                    $this->createErrorFromMessage($event, 'Required query option **' . $key . '** is missing.');

                    return;
                } else {
                    // Replace by default.
                    $queryParameters[$key] = $attribute->default;
                }
            }

            // Add in method attributes.
            $request->attributes->set(
                $attribute->key,
                $queryParameters[$key]
            );
        }

        $request->query->replace($queryParameters);
    }

    private function createError(
        KernelEvent $event,
        string $errorMessage,
        array $errorData = []
    ): void {
        $event->setController(
            fn () => AbstractApiController::apiResponseError(
                $errorMessage,
                $errorData
            )
        );
    }
}
