# bitrix24-application-symfony-template
Bitrix24 application template based on a Symfony framework


## Installation for development

1. Checkout repository
2. call command `make structure-init`
3. call command `make docker-build`
4. call command `make docker-up`
5. call command `make composer-install`
6. call command `make app-migrations-make`
7. call command `make app-migrations-migrate`
8. Start ngrok or another similar tunnel soft

### Add your local application without UI
1. register a new local application without UI into Bitrix24
- add install path `/b24/without-ui/install`
- add handler path `/b24/without-ui/handler`
2. add credentials into `.env.local` file


## Supported application types
- incoming webhook (not supported yet)
- local application without UI (work in progress)
- local application with UI (not supported yet)
- Application for a marketplace (not supported yet)
