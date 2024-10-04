<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Helper\ClassHelper;


abstract class AbstractControllerEventSubscriber implements EventSubscriberInterface
{
    protected function getControllerClassOrMethodAttributes(
        KernelEvent $event,
        string $attributeClass
    ): array {
        $controllerData = $event->getController();

        if (!is_array($controllerData) || !is_subclass_of($controllerData[0], AbstractApiController::class)) {
            return [];
        }

        $requestedClass = $controllerData[0]::class;
        $requestedMethod = $requestedClass.ClassHelper::METHOD_SEPARATOR.$controllerData[1];

        return array_merge(
            ClassHelper::getChildrenAttributes($requestedClass, $attributeClass),
            ClassHelper::getChildrenAttributes($requestedMethod, $attributeClass)
        );
    }
}