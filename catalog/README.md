# catalog/

Contenu de référence du marketplace — pas du code, des **données** : des
exemples d'items publiables (templates AWDL, formulaires, bundles), au format
exact attendu par `POST /api/catalog` (backend). Sert de :

- jeu de données de démonstration/seed pour peupler un `backend/` frais ;
- documentation vivante du format attendu, à côté de la doc d'API elle-même.

Le catalogue **réellement servi** par le marketplace vit dans la base SQLite
du backend (`backend/marketplace.sqlite`, tables `catalog_entry` /
`catalog_entry_version`) — ce dossier n'est jamais lu directement par le
backend au runtime, uniquement par `backend/cli/seed.php` :

```bash
cd backend
php cli/seed.php
```

Idempotent (un `code` déjà présent dans le catalogue est ignoré, jamais
réécrit) et publié sous une instance éditrice dédiée
(« Kibish Approbation (seed) ») directement en statut `approved` — c'est un
jeu de données de démo, pas une soumission à faire revoir.

Les 22 templates actuellement présents ici sont un export réel des workflows
`workflow_template` de `hierarchical-approval-system/back_autohier`
(dépôt séparé), moins les artefacts de test (`e2e_test_template`,
`ext_wait_test`, inactif) — pas des exemples inventés pour l'occasion.

## Format d'un item (`templates/<code>.json`)

```json
{
  "code": "mali_mission_terrain",
  "name": "Demande de mission terrain",
  "description": "Approbation par le manager direct du demandeur.",
  "category": "Terrain",
  "itemType": "template",
  "version": "1.0.0",
  "content": {
    "schema": "awdl/v1",
    "code": "mali_mission_terrain",
    "start_step": "approve",
    "steps": [
      { "id": "approve", "type": "approval", "mode": "single",
        "assignees": [{ "type": "role", "value": "MANAGER" }],
        "transitions": { "approved": "ok", "rejected": "ko" } },
      { "id": "ok", "type": "end", "final_status": "approved" },
      { "id": "ko", "type": "end", "final_status": "rejected" }
    ]
  }
}
```

`itemType` : `template` (config AWDL complète, `content` = le JSON tel
qu'accepté par `POST /workflow-templates` sur une instance back_autohier/
back_php), `form` (`content` = tableau `AwdlFormField[]`, format
`FormDefinition.fields`), ou `bundle` (`content` = manifeste libre, ex.
`{ "files": [...] }`) — le marketplace ne valide pas la forme de `content`,
chaque instance revalide via son propre `AwdlValidatorService` avant import.
