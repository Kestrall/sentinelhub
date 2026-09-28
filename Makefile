DC = docker compose
PHP = $(DC) exec php
CONSOLE = $(PHP) php bin/console

.DEFAULT_GOAL := help
.PHONY: help install up down db seed simulate test logs sh

help: ## Liste les commandes
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

install: up ## Installation complète : conteneurs, dépendances, base, données de démo
	$(PHP) composer install --no-interaction
	$(MAKE) db seed

up: ## Démarre la stack (nginx, PHP-FPM, MySQL, MongoDB)
	$(DC) up -d --build --wait

down: ## Arrête la stack
	$(DC) down

db: ## (Re)crée le schéma MySQL et les index MongoDB
	$(CONSOLE) doctrine:migrations:migrate -n
	$(CONSOLE) doctrine:mongodb:schema:create --index

seed: ## Charge les fixtures + 48 h de télémétrie simulée
	$(CONSOLE) doctrine:fixtures:load -n
	$(CONSOLE) app:simulate --backfill=48

simulate: ## Fait vivre la flotte en temps réel (Ctrl+C pour arrêter)
	$(CONSOLE) app:simulate --live --interval=5

test: ## Lance la suite PHPUnit (unitaires + fonctionnels)
	$(PHP) php bin/phpunit

logs: ## Suit les logs des conteneurs
	$(DC) logs -f

sh: ## Shell dans le conteneur PHP
	$(PHP) bash
