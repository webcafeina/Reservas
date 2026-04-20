# Publicación en GitHub y versionado

Guía operativa para llevar este repositorio local a GitHub, proteger las
ramas, hacer la primera release y mantener el versionado semántico día a
día. Pensada para copiar-pegar comandos en orden.

---

## 0. Requisitos previos

- Cuenta en GitHub con permiso para crear repos en la organización o
  usuario que vaya a alojarlo (típicamente `webcafeina`).
- `git` configurado localmente con tu usuario de GitHub:

```bash
git config --global user.name "Tu Nombre"
git config --global user.email "tu@email.com"
```

- Autenticación con GitHub. **Opción recomendada**: [GitHub CLI](https://cli.github.com/).

```bash
brew install gh           # macOS
gh auth login             # elige HTTPS + login por navegador
```

Alternativa: token personal (PAT) con permisos `repo` + `workflow`, usado
como contraseña al hacer `git push`.

---

## 1. Crear el repositorio en GitHub

### 1.1 Con GitHub CLI (más rápido)

Desde la raíz del proyecto (`/Users/alvaro/Downloads/Reservas 2.0`):

```bash
gh repo create webcafeina/reservas-aldealab \
    --private \
    --description "Sistema autónomo de reservas de salas para Aldealab (Cáceres)." \
    --source=. \
    --remote=origin \
    --push
```

Esto crea el repo **privado**, lo enlaza como `origin` y sube la rama
actual (`develop`) en un solo paso.

> Si prefieres el repo público, cambia `--private` por `--public`. Para
> este cliente recomiendo empezar privado y abrirlo luego si interesa.

### 1.2 Manual desde la web

Si no usas `gh`:

1. Entra a <https://github.com/new>.
2. Owner: `webcafeina` (o tu usuario).
3. Repository name: `reservas-aldealab`.
4. Descripción: *"Sistema autónomo de reservas de salas para Aldealab (Cáceres)."*
5. Visibility: **Private** (recomendado al inicio).
6. **NO** marques "Initialize this repository with a README / .gitignore / license" — ya los tenemos.
7. Create repository.
8. Conecta desde local:

```bash
cd "/Users/alvaro/Downloads/Reservas 2.0"
git remote add origin git@github.com:webcafeina/reservas-aldealab.git
git push -u origin main
git push -u origin develop
```

---

## 2. Subir todas las ramas

Si usaste `gh repo create --push`, solo subiste la rama actual. Asegúrate
de tener ambas en remoto:

```bash
git push -u origin main
git push -u origin develop
```

Comprueba:

```bash
git branch -a
# Debes ver:
#   * develop
#     main
#     remotes/origin/develop
#     remotes/origin/main
```

---

## 3. Proteger las ramas (recomendado)

En GitHub: **Settings → Branches → Add branch protection rule**.

### Regla para `main`

- Branch name pattern: `main`
- ✅ Require a pull request before merging.
    - ✅ Require approvals: 1 (pon 0 si trabajas solo).
- ✅ Require status checks to pass before merging.
    - Después de que CI corra una vez, añade: `php (7.4 / latest)`, `js` (los nombres exactos aparecen tras el primer run).
- ✅ Require branches to be up to date before merging.
- ✅ Do not allow bypassing the above settings.
- ❌ Allow force pushes (déjalo desactivado).
- ❌ Allow deletions.

### Regla para `develop`

Más laxa — aquí iteras día a día:

- Branch name pattern: `develop`
- ❌ Require PR (opcional; si trabajas solo, saltarlo ahorra tiempo).
- ✅ Require status checks: `php`, `js`.
- ❌ Allow force pushes.

---

## 4. Primera release: v0.2.0

El código actual en `develop` ya está en la versión `0.2.0` (ver
`reservas-aldealab.php` y `package.json`). El workflow
`.github/workflows/release.yml` se dispara al empujar un tag `v*` y
empaqueta automáticamente el ZIP de producción.

### 4.1 Merge develop → main

```bash
git checkout main
git merge --no-ff develop -m "release: v0.2.0"
git push origin main
```

Si protegiste `main` con "Require PR", haz PR desde la web:

```bash
gh pr create \
    --base main \
    --head develop \
    --title "release: v0.2.0" \
    --body "$(cat <<'EOF'
## Resumen
Primera release con todo el alcance inicial + roadmap v0.2 completado
(PDF uploader, iCal, SMS opt-in, stats custom range, CSV export).

## Checklist
- [x] CHANGELOG actualizado.
- [x] Version bumpada en reservas-aldealab.php y package.json.
- [x] CI verde en develop.
EOF
)"
```

Mergea el PR desde la web o:

```bash
gh pr merge <N> --merge --delete-branch=false
```

### 4.2 Crear el tag y empujarlo

```bash
git checkout main
git pull origin main
git tag -a v0.2.0 -m "v0.2.0 — PDF uploader, iCal, SMS opt-in, advanced stats, CSV export"
git push origin v0.2.0
```

### 4.3 Observa el workflow de release

Ve a **Actions** en GitHub. Verás un run "Release" ejecutándose:

- Composer install `--no-dev`.
- `npm ci` y `npm run build`.
- Ensamblaje de carpeta limpia con rsync.
- Zip `reservas-aldealab-v0.2.0.zip`.
- Creación del Release en GitHub con el zip adjunto.

Cuando termine en verde, descarga el ZIP desde **Releases** y úsalo para
subirlo al WordPress de producción.

### 4.4 Si el workflow falla

Mira el log del job. Errores típicos:

- **Composer PHP compat**: el runner usa PHP 7.4. Si alguna dependencia exige 8+, fija la versión en `composer.json` o actualiza el workflow.
- **npm ci**: si hay desajuste `package.json` vs `package-lock.json`, haz `npm install` localmente, commitea el lock actualizado, vuelve a tagear.
- **Permisos del `GITHUB_TOKEN`** para crear el release: el workflow ya declara `permissions: contents: write`, así que debería funcionar. Si falla con `403`, verifica en **Settings → Actions → General → Workflow permissions** que está en "Read and write permissions".

---

## 5. Versionado día a día

### 5.1 Flujo de ramas

```
main          o------------o---------o         (releases: v0.2.0, v0.2.1, v0.3.0…)
               \          /         /
develop         o--o--o--o---o--o--o            (integración continua)
                    \  /         \
feat/xxx             oo           o---oo        (trabajo del día)
fix/yyy
```

- **`main`**: solo releases. Cada commit tiene un tag.
- **`develop`**: integración. CI siempre verde aquí.
- **`feat/<nombre>`** o **`fix/<nombre>`**: para cada trabajo concreto.

### 5.2 Trabajar un feature

```bash
git checkout develop
git pull origin develop
git checkout -b feat/migrador-legacy

# … trabaja, comitea …

git push -u origin feat/migrador-legacy
gh pr create --base develop --head feat/migrador-legacy \
    --title "feat: migrador de reservas legacy" \
    --body "Detalles en #issue-X"
```

Tras aprobación + CI verde, merge a `develop`.

### 5.3 Convención de commits

Prefijos que ya uso y que CI / changelog generators reconocen:

- `feat:` nueva funcionalidad.
- `fix:` corrige un bug.
- `docs:` solo documentación.
- `chore:` fontanería (deps, configs).
- `refactor:` sin cambio de comportamiento.
- `test:` tests añadidos o reparados.
- `perf:` mejora de rendimiento.

Un commit por cambio lógico. Mensaje en imperativo: *"feat: add iCal export"*, no *"added iCal"*.

### 5.4 Semver — cuándo incrementar versión

Para un plugin, regla práctica:

| Cambio | Bump |
|---|---|
| Fix de bug sin cambios de API/config | `0.2.0` → `0.2.1` |
| Feature compatible hacia atrás | `0.2.1` → `0.3.0` |
| Cambio que rompe API REST, schema DB o flujo de admin | `0.3.0` → `1.0.0` |

Durante `0.x.x`, el minor puede romper cosas — así es la convención. Al
salir de `0.x` hay que tratarlo más en serio.

### 5.5 Bumpar versión en tres sitios

Cuando vayas a tagear una versión nueva:

1. `reservas-aldealab.php` — cabecera `Version:` y constante `RESERVAS_ALDEALAB_VERSION`.
2. `package.json` — campo `version`.
3. `CHANGELOG.md` — nueva sección con la fecha y el alcance.

```bash
# Ejemplo de bump a 0.2.1
sed -i '' "s/Version:           0.2.0/Version:           0.2.1/" reservas-aldealab.php
sed -i '' "s/RESERVAS_ALDEALAB_VERSION', '0.2.0'/RESERVAS_ALDEALAB_VERSION', '0.2.1'/" reservas-aldealab.php
sed -i '' 's/"version": "0.2.0"/"version": "0.2.1"/' package.json
# Edita CHANGELOG.md a mano con la descripción de lo cambiado.
git add -A
git commit -m "chore: bump version to 0.2.1"
```

Luego repite el ciclo de §4 (merge develop→main, tag, push).

---

## 6. Hotfix en producción

Si aparece un bug crítico en producción mientras `develop` tiene trabajo
a medio hacer que no puedes publicar:

```bash
git checkout main
git pull origin main
git checkout -b fix/hotfix-xxx

# … parchea lo mínimo …
# … bumpa versión a 0.2.1 ( patch ) …

git commit -am "fix: <qué arreglaste>"
git commit -am "chore: bump version to 0.2.1"
git push -u origin fix/hotfix-xxx

gh pr create --base main --head fix/hotfix-xxx \
    --title "fix: <qué arreglaste> [hotfix]"
```

Mergea a `main`, tagea `v0.2.1`, push del tag. Después **mergea `main` de
vuelta a `develop`** para que el fix no se pierda al siguiente release:

```bash
git checkout develop
git merge main
git push origin develop
```

---

## 7. Releases futuras (ejemplo completo)

Siguiente release sería `v0.3.0` cuando completemos el migrador del
legacy. Flujo completo:

```bash
# 1. En develop, bumpa versión y actualiza CHANGELOG
git checkout develop
# ... edita los 3 archivos de §5.5 ...
git commit -am "chore: bump version to 0.3.0"
git push

# 2. Crea PR develop → main
gh pr create --base main --head develop --title "release: v0.3.0"

# 3. Merge tras CI verde.

# 4. Tag en main
git checkout main && git pull
git tag -a v0.3.0 -m "v0.3.0 — migrador automático del plugin legacy"
git push origin v0.3.0

# 5. Descarga el ZIP desde GitHub Releases cuando termine el workflow.
```

---

## 8. Checklist de primera publicación

Cuando estés a punto de hacer la primera release, tacha en orden:

- [ ] `gh repo create` o creación manual desde la web.
- [ ] `git push origin main develop` — ambas ramas en remoto.
- [ ] Primer run de CI pasa en verde (verifica en Actions).
- [ ] Branch protection configurada en `main`.
- [ ] Settings → Actions → General → Workflow permissions = "Read and write".
- [ ] Merge `develop` → `main`.
- [ ] Tag `v0.2.0` + push.
- [ ] Workflow de Release completado → Release publicado con ZIP adjunto.
- [ ] Descargas el ZIP y lo subes al WordPress de producción.
- [ ] Prueba de humo (§3 del README) pasada.

## 9. Seguridad: qué NO subir nunca

El `.gitignore` ya bloquea los casos habituales, pero por si acaso:

- ❌ `vendor/` — dependencias de Composer.
- ❌ `node_modules/` — dependencias de npm.
- ❌ `assets/dist/` salvo `.gitkeep` — build de producción, se genera en CI.
- ❌ Archivos `.env`, `.env.local`, credenciales, tokens.
- ❌ Archivos con datos reales (exports CSV de reservas con nombres y NIFs).
- ❌ Backups `.sql` de producción.

Antes de cada `git push`, si tienes dudas:

```bash
git status
git diff --cached
```

Revisa que no haya secretos colados. Si se te escapa un secreto:

1. Rota la credencial (invalida la que subiste).
2. Limpia el histórico con `git filter-repo` o similar — no solo un nuevo commit, porque queda en el histórico.
3. Fuerza push (con cuidado; por eso `main` está protegido).
