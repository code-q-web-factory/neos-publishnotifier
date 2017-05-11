<?php
namespace CodeQ\PublishNotifier;
use Neos\Flow\Annotations as Flow;
use Neos\SwiftMailer\Message;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Domain\Service\UserService;

class Notifier
{
    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

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
     * Inject the settings
     *
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings) {
        $this->settings = $settings;
    }

    /**
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace
     * @return void
     */
    public function notify($node, $targetWorkspace)
    {
        if ($targetWorkspace->isPrivateWorkspace()) {
            $currentUser = $this->userService->getCurrentUser();
            $userName = $currentUser->getLabel();
            $targetWorkspaceName = $targetWorkspace->getTitle();
            $reviewUrl = sprintf('%1$s/neos/management/workspaces/show?moduleArguments[workspace][__identity]=%2$s', $this->baseUri, $targetWorkspace->getName());

            $senderAddress = $this->settings['senderAddress'];
            $senderName = $this->settings['senderName'];
            $subject = sprintf($this->settings['subject'], $userName);
            $body = sprintf($this->settings['body'], $userName, $targetWorkspaceName, $reviewUrl);

            foreach ($this->settings['notifyEmails'] as $email) {
                $mail = new Message();
                $mail
                ->setFrom(array($senderAddress => $senderName))
                ->setTo(array($email => $email))
                ->setSubject($subject);
                $mail->setBody($body, 'text/plain');
                $mail->send();
            }
        }
    }

}
