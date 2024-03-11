<?php

declare(strict_types=1);

namespace CodeQ\PublishNotifier;

use Neos\ContentRepository\Core\Projection\Workspace\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\Exception\InvalidConfigurationException;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Http\Factories\ServerRequestFactory;
use Neos\Http\Factories\StreamFactory;
use Neos\Neos\Domain\Service\UserService;
use Neos\SwiftMailer\Message;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;

#[Flow\Scope("singleton")]
class Notifier
{
    #[Flow\Inject]
    protected LoggerInterface $systemLogger;

    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected ServerRequestFactory $serverRequestFactory;

    #[Flow\Inject]
    protected StreamFactory $streamFactory;

    #[Flow\InjectConfiguration(path: 'http.baseUri', package: 'Neos.Flow')]
    protected ?string $baseUri;

    #[Flow\InjectConfiguration(path: 'notify', package: 'CodeQ.PublishNotifier')]
    protected ?array $notifySettings;

    #[Flow\InjectConfiguration(path: 'email', package: 'CodeQ.PublishNotifier')]
    protected ?array $mailSettings;

    #[Flow\InjectConfiguration(path: 'slack', package: 'CodeQ.PublishNotifier')]
    protected ?array $slackSettings;

    protected bool $notificationHasBeenSentInCurrentInstance = false;

    /**
     * Send out emails for a change in a workspace.
     *
     * @throws InvalidConfigurationException
     */
    protected  function sendEmails(Workspace $targetWorkspace): void
    {
        if(!$this->mailSettings['enabled']) {
            return;
        }
        if(!$this->mailSettings['senderAddress']) {
            throw new InvalidConfigurationException('The CodeQ.PublishNotifier email.senderAddress configuration does not exist.');
        }
        if(!$this->mailSettings['notifyEmails']) {
            throw new InvalidConfigurationException('The CodeQ.PublishNotifier email.notifyEmails configuration does not exist.');
        }

        // TODO: why current user `null`?
        $currentUser = $this->userService->getCurrentUser();
        $currentUserName = $currentUser?->getLabel() ?: 'null';
        $targetWorkspaceName = $targetWorkspace->workspaceTitle->jsonSerialize();
        $reviewUrl = sprintf('%1$s/neos/management/workspaces/show?moduleArguments[workspace][__identity]=%2$s', $this->baseUri, $targetWorkspace->workspaceTitle->jsonSerialize());

        $senderAddress = $this->mailSettings['senderAddress'];
        $senderName = $this->mailSettings['senderName'];
        $subject = sprintf($this->mailSettings['subject'], $currentUserName);
        $body = sprintf($this->mailSettings['body'], $currentUserName, $targetWorkspaceName, $reviewUrl);

        foreach ($this->mailSettings['notifyEmails'] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidConfigurationException('The CodeQ.PublishNotifier email.notifyEmails entry `' . $email . '` is not valid.');
            }

            try {
                $mail = new Message();
                $mail
                    ->setFrom(array($senderAddress => $senderName))
                    ->setTo(array($email => $email))
                    ->setSubject($subject);
                $mail->setBody($body, 'text/plain');
                $mail->send();
            } catch (\Exception $exception) {
                $this->systemLogger->alert($exception->getMessage());
            }
        }
    }

    /**
     * Send out a Slack message for a change in a workspace.
     *
     * @throws InvalidConfigurationException
     */
    protected function sendSlackMessages(Workspace $targetWorkspace): void
    {
        if (!$this->slackSettings['enabled']) {
            return;
        }
        if (empty($this->slackSettings['postTo'])) {
            throw new InvalidConfigurationException('The CodeQ.PublishNotifier slack.postTo configuration expects at least one target if enabled.');
        }

        // TODO: why current user `null`?
        $currentUser = $this->userService->getCurrentUser();
        $currentUserName = $currentUser?->getLabel() ?: 'null';
        $targetWorkspaceName = $targetWorkspace->workspaceTitle->jsonSerialize();
        $reviewUrl = sprintf('%1$s/neos/management/workspaces/show?moduleArguments[workspace][__identity]=%2$s', $this->baseUri, $targetWorkspace->workspaceTitle->jsonSerialize());

        $message = sprintf($this->slackSettings['message'], $currentUserName, $targetWorkspaceName, $reviewUrl);

        foreach ($this->slackSettings['postTo'] as $postToKey => $postTo) {
            if (empty($postTo['webhookUrl']) || !filter_var($postTo['webhookUrl'], FILTER_VALIDATE_URL)) {
                throw new InvalidConfigurationException('The CodeQ.PublishNotifier slack.postTo ' . $postToKey . ' requires a valid webhookUrl.');
            }

            try {
                $browser = new Browser();
                $engine = new CurlEngine();
                $engine->setOption(CURLOPT_TIMEOUT, 0);
                $browser->setRequestEngine($engine);

                $requestBody = ["text" => $message];

                $slackRequest = $this->serverRequestFactory->createServerRequest('POST', $postTo['webhookUrl'])
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streamFactory->createStream(json_encode($requestBody)));

                $browser->sendRequest($slackRequest);
            } catch (ClientExceptionInterface) {
                $this->systemLogger->warning(sprintf('Could not send message to Slack webhook %s with message "%s"', $postTo['webhookUrl'], $message), LogEnvironment::fromMethodName(__METHOD__));
            }
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function notify(Workspace $targetWorkspace): void
    {
        // skip sending another notification if more than one node is to be published
        if ($this->notificationHasBeenSentInCurrentInstance) {
            return;
        }

        // skip changes to personal workspace
        if ($targetWorkspace->isPersonalWorkspace()) {
            return;
        }

        // skip changes to public/live workspace
        if ($targetWorkspace->isPublicWorkspace() && !$this->notifySettings['publicWorkspace']) {
            return;
        }

        // TODO: implement diff check between Workspaces aka `$isFirstChangeInWorkspace`
//        if($targetWorkspace->isInternalWorkspace()) {
//            $isFirstChangeInWorkspace = !$this->publishingService->getUnpublishedNodes($targetWorkspace);
//
//            if ($isFirstChangeInWorkspace && !$this->notifySettings['internalWorkspace']['onFirstChange']) {
//                return;
//            }
//
//            if (!$isFirstChangeInWorkspace && !$this->notifySettings['internalWorkspace']['onAdditionalChange']) {
//                return;
//            }
//        }

        $this->sendEmails($targetWorkspace);
        $this->sendSlackMessages($targetWorkspace);
        $this->notificationHasBeenSentInCurrentInstance = true;
    }
}
