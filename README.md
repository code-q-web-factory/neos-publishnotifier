# Neos - Publisher Notifier

This package sends notifications to admins every time someone publishes to an internal workspace.

## Usage

1. Install the package: `composer require codeq/publishnotifier`
2. Setup SwiftMailer SMTP credentials if you haven't already.
3. Adjust the settings. See Settings.yaml file for all configuration settings.
4. Make sure your site has `Neos.Flow.http.baseUri` setting set, so your reviewers would get correct urls.

## Possible Improvements

- Implement throttling
- Automatically get email addresses from all live-publishers.
- Show visual diff of changes
