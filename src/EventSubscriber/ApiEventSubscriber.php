<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Doctrine\DBAL\Types\ConversionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
use Wexample\SymfonyApi\Api\Class\ApiValidationErrorData;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Exception\AbstractApiException;
use Wexample\SymfonyApi\Exception\ConstraintViolationException;
use Wexample\SymfonyApi\Exception\DeserializationException;
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
            KernelEvents::EXCEPTION => [
                // Before the kernel logs it (0): what the caller got wrong is
                // logged as such, not as the server failing.
                ['onKernelExceptionClassify', 1],
                ['onKernelException', 0],
            ],
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

                continue;
            }

            // No JSON object to read the DTO from: the client's mistake, said
            // as such, rather than the controller failing without its argument.
            if ($this->requiresArgument($controller, $instance->attributeName)) {
                throw new BadRequestHttpException('The request body must be a JSON object.');
            }
        }
    }

    /**
     * Whether the action cannot run without the argument: neither nullable nor
     * given a default.
     */
    private function requiresArgument(array $controller, string $name): bool
    {
        foreach ((new ReflectionMethod($controller[0], $controller[1]))->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return ! $parameter->allowsNull() && ! $parameter->isDefaultValueAvailable();
            }
        }

        return false;
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

    /**
     * An identifier the database cannot even read — not a uuid where one is
     * stored — names nothing that exists: not found, as any other unknown
     * identifier, rather than the server failing on it.
     */
    public function onKernelExceptionClassify(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof ConversionException
            && RequestHelper::requestIsOnSubClassOf($event->getRequest(), AbstractApiController::class)
        ) {
            $event->setThrowable(new NotFoundHttpException($exception->getMessage(), $exception));
        }
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

            // An exception carrying #[WithHttpStatus] reaches here wrapped by
            // the kernel: the details are the original's.
            if ($exception->getPrevious() instanceof AbstractApiException) {
                $exception = $exception->getPrevious();
            }
        }

        $data = null;
        $message = $exception->getMessage();

        // A body refused by #[ValidateRequestContent] is the caller's mistake.
        if ($exception instanceof ConstraintViolationException || $exception instanceof DeserializationException) {
            $code = Response::HTTP_BAD_REQUEST;
        }

        if ($exception instanceof ConstraintViolationException) {
            $errors = ApiValidationErrorData::create();
            foreach ($exception->getViolations() as $violation) {
                $errors->addIssue(
                    (string) ($violation->getCode() ?? 'INVALID'),
                    $violation->getPropertyPath(),
                    (string) $violation->getMessage()
                );
            }
            $data = $errors->toArray();
        }

        if ($this->parameterBag->get('kernel.debug')) {
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

            $data = ($data ?? []) + [
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
