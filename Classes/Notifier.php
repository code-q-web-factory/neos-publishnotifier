<?php

declare(strict_types=1);

namespace CodeQ\PublishNotifier;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Http\Factories\ServerRequestFactory;
use Neos\Http\Factories\StreamFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Neos\Flow\Configuration\Exception\InvalidConfigurationException;
use Neos\SwiftMailer\Message;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\PublishingServiceInterface;
use Neos\Neos\Domain\Service\UserService;

/**
 * @Flow\Scope("singleton")
 */
class Notifier
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var PublishingServiceInterface
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var ServerRequestFactory
     */
    protected $serverRequestFactory;

    /**
     * @Flow\Inject
     * @var StreamFactory
     */
    protected $streamFactory;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="http.baseUri")
     * @var string
     */
    protected $baseUri;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var bool
     */
    protected $notificationHasBeenSentInCurrentInstance = false;

    /**
     * Inject the settings
     *
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings) {
        $this->settings = $settings;
    }

    /**
     * Send out emails for a change in a workspace.
     *
     * @param Workspace $targetWorkspace
     * @return void
     * @throws InvalidConfigurationException
     */
    protected function sendEmails($targetWorkspace)
    {
        if(!$this->settings['email']['enabled']) {
            return;
        }
        if(!$this->settings['email']['senderAddress']) {
            throw new InvalidConfigurationException('The CodeQ.PublishNotifier email.senderAddress configuration does not exist.');
        }
        if(!$this->settings['email']['notifyEmails']) {
            throw new InvalidConfigurationException('The CodeQ.PublishNotifier email.notifyEmails configuration does not exist.');
        }

        $currentUser = $this->userService->getCurrentUser();
        $currentUserName = $currentUser->getLabel();
        $targetWorkspaceName = $targetWorkspace->getTitle();
        $reviewUrl = sprintf('%1$s/neos/management/workspaces/show?moduleArguments[workspace][__identity]=%2$s', $this->baseUri, $targetWorkspace->getName());

        $senderAddress = $this->settings['email']['senderAddress'];
        $senderName = $this->settings['email']['senderName'];
        $subject = sprintf($this->settings['email']['subject'], $currentUserName);
        $body = sprintf($this->settings['email']['body'], $currentUserName, $targetWorkspaceName, $reviewUrl);

        foreach ($this->settings['email']['notifyEmails'] as $email) {
            try {
                $mail = new Message();
                $mail
                    ->setFrom(array($senderAddress => $senderName))
                    ->setTo(array($email => $email))
                    ->setSubject($subject);
                $mail->setBody($body, 'text/plain');
                $mail->send();
            } catch (\Exception $exception) {
                $this->systemLogger->logException($exception);
            }
        }
    }

    /**
     * Send out a Slack message for a change in a workspace.
     *
     * @param Workspace $targetWorkspace
     * @return void
     * @throws InvalidConfigurationException
     */
    protected function sendSlackMessages($targetWorkspace)
    {
        if(!$this->settings['slack']['enabled']) {
            return;
        }
        if(empty($this->settings['slack']['postTo'])) {
            throw new InvalidConfigurationException('The CodeQ.PublishNotifier slack.postTo configuration expects at least one target if enabled.');
        }

        $currentUser = $this->userService->getCurrentUser();
        $currentUserName = $currentUser->getLabel();
        $targetWorkspaceName = $targetWorkspace->getTitle();
        
        // Generate review Url if not a public Workspace
        if ($targetWorkspace->isPublicWorkspace()) {
            $reviewUrl = sprintf('%1$s/neos/management/workspaces/', $this->baseUri);
        } else {
            $reviewUrl = sprintf('%1$s/neos/management/workspaces/show?moduleArguments[workspace][__identity]=%2$s', $this->baseUri, $targetWorkspace->getName());
        }

        $message = sprintf($this->settings['slack']['message'], $currentUserName, $targetWorkspaceName, $reviewUrl);

        foreach ($this->settings['slack']['postTo'] as $postToKey => $postTo) {
            if (empty($postTo['webhookUrl'])) {
                throw new InvalidConfigurationException('The CodeQ.PublishNotifier slack.postTo ' . $postToKey . ' requires a webhookUrl.');
            }

            try {
                $browser = new Browser();
                $engine = new CurlEngine();
                $engine->setOption(CURLOPT_TIMEOUT, 0);
                $browser->setRequestEngine($engine);

                $requestBody = array(
                    "text" => $message
                );

                $slackRequest = $this->serverRequestFactory->createServerRequest('POST', $postTo['webhookUrl'])
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streamFactory->createStream(json_encode($requestBody)));

                $browser->sendRequest($slackRequest);
            } catch (ClientExceptionInterface $e) {
                $this->systemLogger->warning(sprintf('Could not send message to Slack webhook %s with message "%s"', $postTo['webhookUrl'], $message), LogEnvironment::fromMethodName(__METHOD__));
            }
        }
    }

    /**
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace
     * @return void
     * @throws InvalidConfigurationException
     */
    public function notify($node, $targetWorkspace)
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
        if ($targetWorkspace->isPublicWorkspace() && !$this->settings['notify']['publicWorkspace']) {
            return;
        }

        if($targetWorkspace->isInternalWorkspace()) {
            $isFirstChangeInWorkspace = !$this->publishingService->getUnpublishedNodes($targetWorkspace);

            if ($isFirstChangeInWorkspace && !$this->settings['notify']['internalWorkspace']['onFirstChange']) {
                return;
            }

            if (!$isFirstChangeInWorkspace && !$this->settings['notify']['internalWorkspace']['onAdditionalChange']) {
                return;
            }
        }

        $this->sendEmails($targetWorkspace);
        $this->sendSlackMessages($targetWorkspace);
        $this->notificationHasBeenSentInCurrentInstance = true;
    }
}
