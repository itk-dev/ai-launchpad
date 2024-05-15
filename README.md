# AI Launchpad website


## Site installation for local development

Run the folowing commands to set up the site:

`itkdev-docker-compose up -d`

`itkdev-docker-compose composer install`

`itkdev-docker-compose drush site-install minimal --existing-config -y`

## Sync the drupal config

Export config created from drupal

`itkdev-docker-compose drush config:export`

Import config from config files

`itkdev-docker-compose drush config:export`

## Drupal login

To login to the Drupal admin run the following

`itkdev-docker-compose drush uli --uri="https://launchpad.local.itkdev.dk/"`
