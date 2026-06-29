# Walkthrough — Fase E2: validaciones por campo

**Fecha y hora:** 2026-06-23 12:00 | **Agente:** loop/dev-backend (Claude Code) | **Modelo:** claude-opus-4-8
**Sprint:** S7 (v1.6) | **Versión:** 1.6

---

## Resumen ejecutivo
Los módulos generados ahora validan los datos antes de guardar: muestran errores por campo
y repueblan el formulario con lo ingresado. Reglas: required, email, numeric, integer, unique.

## Cambios realizados
- **`App\Services\Validator`** (nuevo): `make($data, $rules, $table?, $ignoreId?)` → mapa
  campo => primer error. Reglas required/email/numeric/integer/unique (unique con guard
  anti-inyección y soporte de `ignoreId` para edición).
- **`ModuleScaffold::parseRules`**: reglas derivadas del tipo (int→integer, decimal→numeric)
  + explícitas desde `email:string:required,email,unique`.
- **`make-module.php`**: el controlador embebe `RULES` y valida en `store`/`update`; ante
  errores hace flash de `errors`+`old` y vuelve al form; el form repuebla con `old` y
  muestra el error bajo cada campo.

## Verificación
- `php -l` OK (incluye el módulo generado).
- **Suite**: **165/165 PASS** (+5 `Validator`, +2 `parseRules`).
- **E2E (MySQL 3307)**: generé `Contacto` (nombre required, email required+email+unique) →
  (1) submit vacío/mail inválido muestra "obligatorio" y "Email inválido" y repuebla
  `telefono`; (2) submit válido entra ("Ana Lopez"); (3) email duplicado → "Ya existe"
  (unique) y no se duplica.

## Notas
- Módulos demo `Pedido` (E1) y `Contacto` (E2) quedan en la base (regla de no-cleanup).

## Pendientes / follow-ups
- **E3** Exportar listados (CSV / Excel / PDF) — siguiente.

## Referencias
- `system/app/Services/Validator.php`, `system/app/Services/ModuleScaffold.php`,
  `system/console/make-module.php`, `tests/unit/ValidatorTest.php`.
