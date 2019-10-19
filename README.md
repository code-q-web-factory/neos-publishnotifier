# Neos CMS - Publisher Notifier

This package sends notifications every time someone publishes to an internal workspace. If the internal workspace 
already has unpublished changes it will not send notifications anymore to not spam slack channels or email inboxes.

Simply install the package via composer:

```bash
composer require codeq/publishnotifier
```

Make sure your site has `Neos.Flow.http.baseUri` setting set, so your reviewers would get correct urls.

## Configuration for email notifications

In order to send messages to emails you need to add configure the [neos/swiftmailer](https://swiftmailer-for-flow.readthedocs.io/en/latest/) credentials 

Then you need to configure the target email addresses, together with the email content:
 
```yaml
CodeQ:
  PublishNotifier:
    email:
      enabled: false
      senderName: 'Neos'
      senderAddress: 'no-reply@neos-server.com'
      notifyEmails:
        - 'notifyme@example.com'
      subject: '%1$s has published changes'
      body: |+
        %1$s has published changes to the private workspace %2$s.
        Please review the changes and publish to live: %3$s'
```

## Configuration for slack messages

In order to send messages to Slack you need to add an incoming WebHook to your Slack workspace. Read more about it here [https://api.slack.com/incoming-webhooks](https://api.slack.com/incoming-webhooks)

As the incoming webhooks are treated as Slack Apps they are bound to a single channel. Therefore you can configure multiple "postTo" to use several webhooks:

```yaml
CodeQ:
  PublishNotifier:
    slack:
      enabled: false
      postTo: []
        myExampleTarget:
          webhookUrl: 'https://hooks.slack.com/services/...'
          clientSettings: [] # additional client configurations
      message: |+
        %1$s has published changes to the private workspace %2$s.
        Please review the changes and publish to live: %3$s'
```

Read more about the possible client settings and options here: https://github.com/maknz/slack#settings


## Possible Improvements

- Automatically get email addresses from all live-publishers.
- Show visual diff of changes
