<?php

namespace CodeQ\PublishNotifier\CatchUpHook;

use CodeQ\PublishNotifier\Notifier;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Event\WorkspaceWasPartiallyPublished;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Event\WorkspaceWasPublished;
use Neos\ContentRepository\Core\Projection\CatchUpHookInterface;
use Neos\EventStore\Model\EventEnvelope;
use Neos\Flow\Configuration\Exception\InvalidConfigurationException;

final readonly class WorkspacePublishHook implements CatchUpHookInterface
{
    public function __construct(
        private ContentRepository $contentRepository,
    ) {
    }

    public function onBeforeCatchUp(): void
    {
        // Nothing to do here
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        // Nothing to do here
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        match ($eventInstance::class) {
            WorkspaceWasPublished::class => $this->sendNotification($eventInstance),
            WorkspaceWasPartiallyPublished::class => $this->sendNotification($eventInstance),
            default => null
        };
    }

    public function onBeforeBatchCompleted(): void
    {
        // Nothing to do here
    }

    public function onAfterCatchUp(): void
    {
        // Nothing to do here
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function sendNotification(EventInterface $event): void
    {
        $workspaceFinder = $this->contentRepository->getWorkspaceFinder();

        /** @var WorkspaceWasPublished|WorkspaceWasPartiallyPublished $event */
        (new Notifier)->notify($workspaceFinder->findOneByName($event->targetWorkspaceName));
    }
}
