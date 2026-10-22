<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyApi\Event\ApiBatchEvent;

/**
 * The default recorder of received batches, on a channel of their own.
 */
#[WithMonologChannel('api_batch')]
class ApiBatchLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ApiBatchEvent::class => 'onApiBatch'];
    }

    public function onApiBatch(ApiBatchEvent $event): void
    {
        $this->logger->info('api.batch_received', $event->toArray());
    }
}
