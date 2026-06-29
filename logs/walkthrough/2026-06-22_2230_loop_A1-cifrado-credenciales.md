# Walkthrough — Fase A1: cifrado en reposo de credenciales

**Fecha y hora:** 2026-06-22 22:30 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S2 (v1.1) | **Versión:** 1.0.0 → 1.1

---

## Resumen ejecutivo
Las credenciales sensibles (contraseña SMTP y API key de IA) ahora se cifran en reposo
con AES-256-GCM usando una `APP_KEY`. El cifrado/descifrado es transparente vía
`Settings`. Verificado con 7 tests nuevos (round-trip incluido contra MySQL).

## Cambios realizados
- **`Core\Crypto`**: `encrypt`/`decrypt` (AES-256-GCM autenticado), `isEncrypted`,
  `maybeDecrypt`, `generateKey`; degrada a texto plano si falta `APP_KEY`.
- **`Settings`**: `get()` y `group()` descifran automáticamente; nuevo `setSecret()`
  que cifra antes de persistir.
- **Controladores**: `MailController` (pass) y `AiController` (api_key) usan `setSecret`.
- **CLI**: `system/console/key.php` genera la `APP_KEY`.
- **.env / .env.example**: `APP_KEY` agregada (clave real generada en el `.env` local).

## Verificación
- `php -l` OK en todos los archivos tocados.
- **Suite**: `php tests/run.php` → **44/44 PASS** (antes 37; +6 unit Crypto, +1 feature
  Settings cifrado).
- Round-trip real (feature, MySQL 3307): `setSecret('test.secret', …)` deja en la tabla
  un valor `enc::…` (cifrado) y `Settings::get` lo devuelve descifrado. Payload alterado
  → no descifra (autenticación GCM).

## Decisiones de diseño
- Cifrado autenticado (GCM) para detectar manipulación.
- Descifrado transparente en la capa `Settings` → los servicios (Mailer/AiConnector) no
  cambian: siguen leyendo `group()` y reciben el valor en claro.
- Degradación controlada sin `APP_KEY` (no rompe; queda en texto plano con aviso en docs).

## Pendientes / follow-ups
- **A2** Hardening de login (rate-limit/lockout, mi-perfil, cambiar contraseña) — siguiente.
- A futuro: rotación de `APP_KEY` (re-cifrado de secretos).

## Referencias
- `system/app/Core/Crypto.php`, `system/app/Services/Settings.php`,
  `tests/unit/CryptoTest.php`, `tests/feature/SettingsSecretTest.php`.
