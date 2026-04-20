# ADR 003 — Bundle separado para el panel admin

- Estado: Aceptado
- Fecha: 2026-04-20
- Autor: Equipo Reservas Aldealab

## Contexto

El plugin tiene dos superficies React independientes:

1. **SPA pública** (`frontend/src/main.tsx`) — formulario de reserva en 8 pasos.
   Se monta en `#reservas-app`, inyectado por el shortcode. Lo usan usuarios
   anónimos o logueados.
2. **Panel admin** (`frontend/admin/main.tsx`) — dashboard, listado, editor
   de reservas y ajustes. Se monta en `#reservas-admin-app` dentro de la
   página de admin de WordPress. Solo para usuarios con la capacidad
   `manage_reservas`.

Las dos aplicaciones comparten muy poco: tipos de modelo y algunos
componentes (Button, Field). Todo lo demás (pasos del flujo vs. rutas del
admin, Zustand store para el wizard vs. TanStack Query puro) es distinto.

## Decisión

Construimos **dos bundles separados** vía Vite multi-entry:

```ts
build.rollupOptions.input = {
    app:   resolve(__dirname, 'frontend/src/main.tsx'),    // SPA pública
    admin: resolve(__dirname, 'frontend/admin/main.tsx'),  // Panel admin
}
```

Cada uno produce su propio JS + CSS con hash. `manifest.json` los lista por
separado y los loaders PHP enqueuean el correspondiente:

- `Frontend\AssetLoader` (público) → entrada `src/main.tsx`, solo en páginas
  con el shortcode `[reservas_aldealab_formulario]`.
- `Admin\AdminAssetLoader` → entrada `admin/main.tsx`, solo en la página
  `admin.php?page=reservas-aldealab`.

## Razones

- **Peso**: los usuarios del formulario no descargan 50 KB de código admin
  que nunca van a usar, y viceversa.
- **Separación de dependencias**: si mañana el admin necesita una librería
  de tablas con sorting + CSV export, no infla el bundle público.
- **Aislamiento de errores**: un bug en una vista admin no bloquea el
  formulario de reserva.
- **Código compartido sigue compartido**: ambos paquetes importan de
  `frontend/src/types/`, `frontend/src/api/client.ts` y
  `frontend/src/components/` según necesiten. Vite dedupe los chunks.

## Alternativas descartadas

- **App única con react-router**: añade dependencia, mezcla contextos de
  usuario y oculta la separación cognitiva con un wrapper de rutas.
- **SSR / Gutenberg blocks**: overkill para un CRUD interno.

## Consecuencias

- El `tsconfig.json` incluye tanto `frontend/src` como `frontend/admin`.
- Los tests de Vitest corren sobre ambos árboles (ya configurado con
  `include: 'frontend/**/*.{test,spec}.{ts,tsx}'`).
- El release workflow (Fase 10) empaqueta ambos bundles en `assets/dist/`
  y los dos loaders saben localizarlos.
