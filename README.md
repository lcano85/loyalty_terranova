# Terranova Loyalty

Webapp de fidelización para Terranova Restobar, inspirada en Stampee y las capturas proporcionadas. Frontend **Next.js + React + TypeScript** y API **PHP 8.2+ + MySQL 8**. No requiere Supabase ni un backend Node.js. Next.js sí requiere Node.js para compilar y servir la interfaz.

## Actualización: fecha de nacimiento opcional

En Clientes → Agregar/Editar se puede guardar o borrar la fecha de nacimiento. Se almacena como `birth_date DATE NULL`. La API valida el calendario y rechaza fechas futuras; una actualización que no envíe este campo conserva el valor existente. Los clientes anteriores quedan sin fecha.

Para una base existente, ejecuta `php backend/bin/migrate.php` desde la raíz local (o `php bin/migrate.php` desde el backend del servidor). El comando es repetible. Si no tienes terminal en el hosting, ejecuta una sola vez el SQL de `backend/database/migrations/20261008_customer_birth_date.sql` en phpMyAdmin sobre la base principal. No vuelvas a importar toda la base.

Sube los cambios del backend y recompila el frontend actualizado. La prueba `php backend/tests/customer-birth-date.php` valida fechas y MySQL sin dejar datos ficticios ni recrear la base de pruebas.

## Estructura

```text
backend/             API PHP, esquema MySQL, instalación y pruebas
  .env               Credenciales y configuración privada
  .env.example       Plantilla sin credenciales reales
  public/            Único directorio que se expone por HTTP
  database/schema.sql
frontend/            Aplicación Next.js
  .env               URL de la API y de la aplicación
  .env.example
reference/stampee/   Copia local del repositorio original (excluida de Git)
LICENSE-STAMPEE      Licencia MIT y atribución original
```

Los dos archivos `.env` ya están creados. Ningún secreto del backend debe copiarse al frontend. Las variables `NEXT_PUBLIC_*` son públicas. Los `.env` y archivos de sesión están excluidos de Git.

## Ejecutar en Windows / Laragon

Desde `D:\proyectos\loyalty_terranova`:

1. Configura `backend/.env` con tus credenciales MySQL. El usuario necesita permiso para crear la base de datos durante la instalación. Para un servidor con permisos restringidos, crea previamente la base y otorga permisos sobre ella.
2. Inicializa la base y la cuenta de propietario:

   ```powershell
   php backend/bin/setup.php
   ```

   El instalador crea las tablas, una cuenta con `SEED_OWNER_EMAIL` y `SEED_OWNER_PASSWORD` y una campaña Terranova de 6 compras. No crea clientes ficticios ni cambia contraseñas de cuentas existentes. Cambiar los valores `SEED_*` después de la instalación no actualiza la cuenta: cambia la contraseña desde Configuración.

3. En una terminal, inicia la API:

   ```powershell
   php -S localhost:6010 -t backend/public backend/public/router.php
   ```

4. En otra terminal, inicia Next.js:

   ```powershell
   cd frontend
   npm install
   npm run dev
   ```

5. Abre `http://localhost:6012` y accede con los valores `SEED_OWNER_*` usados al instalar.

Usa `localhost` tanto para la API como para el frontend; no mezcles `localhost` y `127.0.0.1` en las URL del navegador, porque las cookies de sesión pertenecen al host. `DB_HOST=127.0.0.1` puede mantenerse.

## Módulos

- Dashboard con primeros pasos, contadores y actividad reciente.
- Campañas: creación, edición de texto, recompensa, colores y meta; activación y desactivación; QR público. La eliminación solo se permite si no hay tarjetas emitidas.
- Clientes: registro, edición, búsqueda y número de tarjetas activas.
- Tarjetas emitidas: búsqueda, emisión, QR y consulta pública mediante token aleatorio.
- Kiosco: agregar o retirar un sello, observaciones, canjear y revocar tarjetas.
- Escaneo QR por cámara y alternativa con enlace o ID manual. La cámara requiere HTTPS, salvo en localhost.
- Transacciones: historial con autor y rol, búsqueda, filtro de fecha, orden y exportación CSV.
- Analítica: rango de fechas, actividad diaria, canjes y progreso por campaña. Los saldos son el estado actual de las tarjetas que tuvieron actividad en el rango, no una reconstrucción histórica de saldos.
- Configuración: perfil, contraseña y creación/activación/desactivación de personal.
- Registro público por campaña (`/join/:id`) y tarjeta del cliente (`/card/:token`). Un correo existente requiere atención en el local; el registro público no permite recuperar tarjetas por correo sin verificar identidad.

El propietario administra campañas, perfil y personal. El personal gestiona clientes, tarjetas, sellos y canjes. La API valida estos permisos aunque se acceda directamente a una ruta. No hay registro público de negocios: esta instalación corresponde a Terranova.

## Reglas del programa

Cada tarjeta conserva el diseño y la meta vigentes al momento de su emisión. Al completar los sellos se puede canjear una sola vez; la tarjeta queda como canjeada y se emite una nueva para el siguiente ciclo. No se pueden agregar sellos a una tarjeta canjeada o revocada. El backend usa bloqueos de filas y transacciones para guardar conjuntamente el saldo y el historial. Las tarjetas revocadas conservan el historial, pero su enlace público deja de funcionar.

La consulta pública no entrega nombre, teléfono ni correo del cliente. El enlace contiene un token aleatorio que debe conservar el cliente. Las contraseñas se guardan con `password_hash`; la sesión usa cookies HttpOnly y protección CSRF. Hay límites de intentos de login y registro público.

## Verificación

```powershell
cd frontend
npm run typecheck
npm run build
```

Prueba de integración en una base **separada** `loyalty_terranova_test`:

```powershell
php backend/tests/provision.php
$env:DB_DATABASE='loyalty_terranova_test'
php -S localhost:8001 -t backend/public backend/public/router.php
```

Desde otra terminal en la raíz:

```powershell
node backend/tests/integration.mjs
```

La prueba crea registros QA únicamente en la base de prueba y verifica autenticación, CSRF, permisos, validaciones, emisión, snapshots, sellos, canje, historial, revocación, inscripción pública y campañas desactivadas. En la terminal de pruebas, elimina la variable `DB_DATABASE` antes de iniciar la API real: `Remove-Item Env:DB_DATABASE`.

## Despliegue

Sirve únicamente `backend/public` con Apache/PHP o Nginx/PHP-FPM. `.htaccess` incluye el enrutamiento de Apache. El usuario de PHP debe poder escribir en `backend/storage/sessions`. No expongas la raíz del backend, los `.env` ni la copia de referencia.

Ejecuta `npm run build` y `npm run start` para Next.js. Configura las URL reales antes de compilar. Para conservar la sesión con SameSite=Lax, usa la misma raíz de dominio, por ejemplo `loyalty.tudominio.com` y `api.tudominio.com`, o un proxy de `/api`. Configura `FRONTEND_URL` con el origen exacto y `SESSION_SECURE=true` bajo HTTPS. El servidor PHP incorporado solo se usa para desarrollo.

## Referencia y alcance de la adaptación

Repositorio original: https://github.com/danlim26/stampee

Copia revisada: `169be021e747308cac39365f85f72273e6e318ca`.

Stampee usa React/Vite, Supabase Auth, PostgreSQL, Storage y funciones RPC. Esta versión implementa el flujo principal con API PHP/MySQL y enrutamiento Next.js. Conserva el repositorio original y su licencia para consulta. La interfaz está en español y adaptada a Terranova; no es una reproducción píxel por píxel. El logo es tipográfico provisional. No incluye todavía carga de logos/fondos, todas las opciones del editor visual original, enlaces sociales, correo de recuperación de contraseña, integración Apple/Google Wallet ni eliminación total del negocio.

El desarrollo usa el puerto 6012 (`npm run dev`) y la carpeta `.next-dev`; el servidor de producci?n permanece en 6011 (`npm run start`) con `.next`. La API permite ambos or?genes locales mediante `FRONTEND_ALLOWED_ORIGINS`.
