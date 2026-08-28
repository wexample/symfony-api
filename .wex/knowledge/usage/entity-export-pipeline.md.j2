# Entity Export Pipeline (PHP → TypeScript)

Pipeline complet pour exporter des entités PHP vers des classes TypeScript consommables côté JS.

## Étapes dans l'ordre

### 1. Générer le YAML pseudocode (symfony-pseudocode)

```bash
php bin/console pseudocode:generate:pseudocode
```

Pré-requis : l'entité PHP doit avoir `#[PseudocodeExport]`. Si elle vient d'un bundle vendor, ce bundle doit implémenter `PseudocodeBundleInterface`.

Produit : `pseudocode/entity/<entity>.yml`

### 2. Exporter le JSON (symfony-api)

```bash
php bin/console api:export:entities
```

Options : `--source` (défaut: `pseudocode/entity`), `--output` (défaut: `front/data/entity`), `--visibility` (défaut: `public`).

Produit : `front/data/entity/<entity>.json`

### 3. Générer les classes TypeScript (js-api)

```bash
node node_modules/@wexample/js-api/bin/generate-entities.mjs
node node_modules/@wexample/js-api/bin/generate-repositories.mjs
```

Options : `--data-dir` (défaut: `front/data/entity`), `--output-dir` (défaut: `front/js`).

Produit : `front/js/Entity/<Entity>.ts`, `front/js/Repository/<Entity>Repository.ts`, `front/js/Common/generatedEntitySchemas.ts`, `front/js/Common/generatedRepositories.ts`

## Entités issues d'un package vendor

Si l'entité est définie dans un package npm (ex: `@wexample/js-money`), ajouter dans le YAML et le JSON :

```yaml
package: "@wexample/js-money"
```

Les scripts JS skipperont la génération locale mais incluront quand même l'import depuis le package dans les manifestes générés.

## Câblage dans l'app

Le client API doit retourner les repos via `getRepositoryClasses()` :

```typescript
// AppMyApp.ts
createApiClient() {
  return MyApiClient.create({ baseUrl: '/api/' });
}

// MyApiClient.ts
protected getRepositoryClasses() {
  return generatedRepositories;
}
getEntitySchemas() {
  return getGeneratedEntitySchemas();
}
```
