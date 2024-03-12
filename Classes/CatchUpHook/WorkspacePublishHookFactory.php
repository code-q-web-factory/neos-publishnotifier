<?php

namespace CodeQ\PublishNotifier\CatchUpHook;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\CatchUpHookInterface;

class WorkspacePublishHookFactory implements CatchUpHookFactoryInterface
{
    public function build(ContentRepository $contentRepository): CatchUpHookInterface
    {
        return new WorkspacePublishHook(
            $contentRepository,
        );
    }
}
