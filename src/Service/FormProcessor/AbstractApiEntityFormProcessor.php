<?php

namespace Wexample\SymfonyApi\Service\FormProcessor;

use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyForms\Form\Data\EntityEditFormData;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;

abstract class AbstractApiEntityFormProcessor extends AbstractFormProcessor
{
    private ?array $lastResponse = null;

    abstract protected static function getEntityClass(): string;

    abstract protected function getApiClient(): object;

    abstract protected function getApiEndpointPart(): string;

    protected function getApiEndpoint(): string
    {
        return $this->getApiClient()->buildEntityEntrypoint(
            static::getEntityClass(),
            $this->getApiEndpointPart()
        );
    }

    protected function getApiMethod(): string
    {
        return 'POST';
    }

    public function onValid(FormInterface $form)
    {
        $this->lastResponse = $this->getApiClient()->requestJson(
            $this->getApiMethod(),
            $this->getApiEndpoint(),
            [
                'json' => $this->getApiPayload($form),
            ]
        );

        if ($redirectUrl = $this->getSuccessRedirectUrl($form)) {
            $this->redirect($redirectUrl);
        }
    }

    public function getSuccessRedirectUrl(FormInterface $form): ?string
    {
        if (! $this->urlGenerator) {
            return null;
        }

        $routeName = $this->getSuccessRedirectRouteName($form);
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        return $this->urlGenerator->generate(
            $routeName,
            $this->getSuccessRedirectRouteParameters($form)
        );
    }

    protected function getSuccessRedirectRouteName(FormInterface $form): ?string
    {
        $controllerClass = $this->getSuccessRedirectControllerClass();

        if (! is_string($controllerClass) || $controllerClass === '') {
            return null;
        }

        if (! defined($controllerClass . '::ROUTE_INDEX')) {
            return null;
        }

        return $controllerClass::buildRouteName(
            $controllerClass::ROUTE_INDEX
        );
    }

    protected function getSuccessRedirectRouteParameters(FormInterface $form): array
    {
        return $this->getNestedRouteParameters();
    }

    protected function getSuccessRedirectControllerClass(): ?string
    {
        return null;
    }

    protected function getNestedRouteParameters(): array
    {
        $attributes = $this->request?->attributes?->all() ?? [];
        $params = [];

        foreach ($attributes as $key => $value) {
            if ($key === 'secureId') {
                continue;
            }

            if (
                is_string($key)
                && str_ends_with($key, 'SecureId')
                && is_string($value)
                && $value !== ''
            ) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    protected function getApiPayload(FormInterface $form): array
    {
        $data = $form->getData();
        if ($data instanceof EntityEditFormData) {
            $data = $data->getFormData();
        }

        if (! is_array($data)) {
            throw new RuntimeException('Expected array data for api entity form.');
        }

        $fieldNames = $this->getPayloadFieldNames($form, $data);
        $payload = [];

        foreach ($fieldNames as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $payload[$fieldName] = $data[$fieldName];
            }
        }

        return $payload;
    }

    protected function getPayloadFieldNames(FormInterface $form, array $data): array
    {
        return array_keys($data);
    }

    protected function getLastResponseData(): ?array
    {
        $data = $this->lastResponse['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    protected function getLastResponseEntity(): ?array
    {
        $data = $this->getLastResponseData();
        $entity = is_array($data) ? ($data['entity'] ?? null) : null;

        return is_array($entity) ? $entity : null;
    }
}
