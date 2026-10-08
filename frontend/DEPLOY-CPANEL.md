# Publicar Terranova en cPanel

Este paquete contiene el proyecto fuente. Necesita un hosting con Node.js (Setup Node.js App / Application Manager) y capacidad para compilar Next.js. No es un conjunto de archivos estáticos para un hosting exclusivo de PHP.

## 1. Dominio y SSL

Crea qr.terranovarestobar.com en cPanel, con DNS apuntando al hosting y certificado HTTPS activo. Conserva adminqr.terranovarestobar.com para la API PHP.

## 2. Subir

Extrae los archivos del ZIP en una carpeta de aplicación, por ejemplo /home/desaweba/loyalty_frontend. Dentro de esa carpeta deben estar directamente package.json, app.cjs, next.config.mjs, app/, components/ y lib/. No dejes una carpeta frontend adicional entre ellos.

No subas node_modules, .next, .next-dev, .env local ni archivos del backend. Las dependencias se instalarán para Linux en el servidor. No uses la compilación local actual: contiene URL localhost.

## 3. Crear aplicación Node.js

En Setup Node.js App configura:

| Campo | Valor |
|---|---|
| Node.js version | 22, si está disponible |
| Application mode | Production |
| Application root | loyalty_frontend (relativa al directorio home) |
| Application URL | qr.terranovarestobar.com, ruta / |
| Application startup file | app.cjs |

Si usas Application Manager de cPanel en lugar de Setup Node.js App, registra la misma carpeta y dominio; el proveedor puede necesitar configurar PassengerStartupFile app.cjs. No publiques la carpeta como un directorio de archivos sin configurar el servidor Node.

El archivo app.cjs permite arrancar Next.js mediante Passenger. No ejecutes npm run dev en producción ni asignes el puerto local 6012 en el panel. El hosting administra la conexión entre HTTPS y Node.js.

## 4. Variables de producción

Copia .env.production.example como .env.production en la raíz de la aplicación. Contiene:

```env
NEXT_PUBLIC_API_URL=https://adminqr.terranovarestobar.com/api
NEXT_PUBLIC_APP_URL=https://qr.terranovarestobar.com
```

Configura también NODE_ENV=production en la aplicación. Si el panel ya tiene variables NEXT_PUBLIC_*, actualízalas: esas variables tienen prioridad sobre el archivo. Las variables NEXT_PUBLIC_* se incluyen durante la compilación; cambiarlas exige recompilar.

## 5. Instalar y compilar

En la terminal / SSH del hosting, activa primero el entorno Node.js usando el comando que muestra Setup Node.js App. No adivines la ruta del entorno.

Después, desde la carpeta de la aplicación:

```bash
npm ci --include=dev
npm run build
```

Se incluyen las dependencias de desarrollo porque TypeScript se necesita para compilar. Si el proveedor gestiona node_modules mediante un enlace del selector y npm ci falla por ese enlace, usa el botón Run NPM Install o npm install --include=dev en el entorno activado; luego ejecuta npm run build.

Cuando termine la compilación, pulsa Restart / Reiniciar en el panel Node.js. La aplicación puede mostrar un error hasta que exista .next/BUILD_ID. No dejes la aplicación arrancada únicamente desde una terminal SSH: usa el administrador del hosting para su ciclo de vida.

## 6. Backend

En el .env del backend del hosting conserva las credenciales MySQL y configura:

```env
APP_ENV=production
APP_URL=https://adminqr.terranovarestobar.com
FRONTEND_URL=https://qr.terranovarestobar.com
FRONTEND_ALLOWED_ORIGINS=https://qr.terranovarestobar.com
SESSION_SECURE=true
```

## 7. Comprobar

1. https://adminqr.terranovarestobar.com/api/health debe devolver {"ok":true,"database":true}.
2. Abre https://qr.terranovarestobar.com/login y entra con la cuenta importada en MySQL.
3. En Campañas abre un QR. El enlace debe comenzar por https://qr.terranovarestobar.com/join/ y no contener localhost.
4. Abre ese enlace desde un celular para comprobar el registro y consulta de la tarjeta.

Si aparece 503, revisa el log de Passenger: versión Node.js, dependencias y compilación. Si aparece Origen no autorizado, revisa FRONTEND_ALLOWED_ORIGINS. Si el build falla por límites de RAM o procesos del hosting, pide al proveedor capacidad para compilar/ejecutar Next.js o usa otro hosting Node.js para el frontend conservando el dominio personalizado. Si no existe ninguna opción Node.js, consulta al proveedor antes de continuar: este ZIP no puede servirse directamente con PHP.
