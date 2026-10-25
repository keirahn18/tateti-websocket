<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Ta-Te-Ti en tiempo real

Crea o busca una partida desde la página principal. Cada partida admite exactamente dos jugadores: si hay una sala esperando, el próximo jugador entra en esa misma partida; si no, se crea una sala nueva y se muestra **Esperando al segundo jugador**. Cuando una sala ya tiene dos jugadores, los siguientes se emparejan en otra sala en vez de quedarse mirando. También puedes compartir el enlace/ID de la partida: al entrar, el visitante se asigna a un lugar disponible de esa sala. Si una sesión ya tenía una sala propia vacía, se libera su ficha al cambiar a otra sala. La identidad se conserva mediante la sesión del navegador, no por dirección IP, así que dos jugadores detrás de la misma red pueden participar por separado.

La ficha asignada se valida en el servidor y permanece reservada durante la partida. Al ganar, perder o empatar se liberan las dos fichas; el botón **Reiniciar partida** vacía el tablero y permite que los visitantes vuelvan a ocuparlas. Después de actualizar el proyecto, aplica las migraciones con `php artisan migrate`.

### Probar desde Internet con Cloudflare Tunnel

Para una prueba temporal sin dominio propio se necesitan dos túneles Quick Tunnel: uno para Laravel y otro para Reverb. Abre varias ventanas de PowerShell y ejecuta desde la carpeta del proyecto.

1. Instala Cloudflare Tunnel si todavía no está instalado:

   ```powershell
   winget install --id Cloudflare.cloudflared
   ```

2. Configura Reverb una sola vez y aplica las migraciones:

   ```powershell
   php artisan reverb:install
   php artisan migrate
   ```

   Si ya tenías Reverb configurado, no hace falta repetir la instalación.

3. En la primera ventana, inicia la web:

   ```powershell
   Set-Location C:\xampp\htdocs\tateti-websocket\tateti-websocket
   php artisan serve --host=0.0.0.0 --port=8000
   ```

4. En la segunda ventana, inicia Reverb:

   ```powershell
   Set-Location C:\xampp\htdocs\tateti-websocket\tateti-websocket
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```

5. En la tercera ventana, crea el túnel de Reverb y copia el hostname HTTPS que imprime Cloudflare (sin `https://`):

   ```powershell
   cloudflared tunnel --url http://localhost:8080
   ```

6. En `.env`, conserva la configuración interna de Reverb apuntando al servidor local. Cambia solo el hostname público de Vite por el hostname copiado:

   ```dotenv
   APP_URL=https://HOST-WEB.trycloudflare.com
   APP_DEBUG=false
   SESSION_SECURE_COOKIE=true
   BROADCAST_CONNECTION=reverb
   QUEUE_CONNECTION=sync
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8080
   REVERB_SCHEME=http
   REVERB_SERVER_HOST=0.0.0.0
   REVERB_SERVER_PORT=8080
   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST=HOST-REVERB.trycloudflare.com
   VITE_REVERB_PORT=443
   VITE_REVERB_SCHEME=https
   ```

   Reemplaza ambos hostnames con los valores que te asignó Cloudflare. No cambies `REVERB_HOST`: Laravel lo usa para enviar los eventos a Reverb localmente; `VITE_REVERB_HOST` es la dirección pública que usa el navegador. `QUEUE_CONNECTION=sync` es práctico para esta prueba local, porque procesa los eventos al instante sin abrir un worker. No uses este modo como sustituto de una cola configurada para producción.

7. En la cuarta ventana, reconstruye los assets y limpia la configuración almacenada:

   ```powershell
   Set-Location C:\xampp\htdocs\tateti-websocket\tateti-websocket
   if (Test-Path public\hot) { Remove-Item public\hot }
   npm run build
   php artisan optimize:clear
   ```

   Si tienes una ventana ejecutando `npm run dev`, detenla con `Ctrl+C` antes de compartir la web. Mientras exista `public\hot`, Laravel intenta cargar CSS y JavaScript desde el servidor de Vite local (puerto 5173), inaccesible para quienes entran por Cloudflare. El paso anterior elimina ese indicador y sirve los assets compilados.

8. En una quinta ventana, abre el túnel de la web:

   ```powershell
   cloudflared tunnel --url http://localhost:8000
   ```

   Copia el hostname que aparece y reemplaza `HOST-WEB.trycloudflare.com` en `APP_URL`. Luego ejecuta `php artisan optimize:clear` otra vez.

Comparte la URL HTTPS del túnel de la web. Laravel confía en las cabeceras HTTPS reenviadas solo cuando la conexión al servidor local viene desde loopback, como sucede con `cloudflared`; así los enlaces de CSS y las redirecciones se generan con HTTPS. `APP_DEBUG=false` evita mostrar detalles de errores a quienes abran el túnel. Ambos procesos (`artisan serve`, `reverb:start`) y ambos túneles deben permanecer abiertos. Los Quick Tunnels generan direcciones aleatorias: al reiniciarlos tendrás que actualizar las URLs en `.env`; si cambia la URL de Reverb, vuelve a ejecutar `npm run build`. Para uso continuo, configura un Named Tunnel con un dominio propio.

Si la partida no se actualiza en vivo, confirma que el túnel de Reverb para `localhost:8080` sigue abierto y que `VITE_REVERB_HOST` contiene su hostname real, sin `https://` (no el texto `HOST-REVERB.trycloudflare.com`). Después elimina `public\hot` si existe, ejecuta `npm run build` y `php artisan optimize:clear`, y recarga la página. El indicador **WebSocket** de la partida debe decir **Conectado: escuchando esta partida**. Al suscribirse o reconectarse, el navegador también consulta el estado actual para sincronizar tablero y jugadores.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
