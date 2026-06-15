# Stelfaro Notifications

Servicio Laravel liviano para mensajeria fiscal del ecosistema Stelfaro.

El core DTE se mantiene como fuente de verdad fiscal. Este servicio se encarga de solicitar artefactos oficiales al core, preparar correos, adjuntar PDF/JSON y registrar estados de entrega.

## Primer contrato

Solicitud interna para enviar un DTE aceptado por correo:

```http
POST /api/v1/dte/{document}/email
Authorization: Bearer {NOTIFICATIONS_API_TOKEN}
Content-Type: application/json
```

```json
{
  "empresa_id": 1,
  "recipient": {
    "name": "Cliente Demo",
    "email": "cliente@example.test"
  },
  "subject": "Su factura electronica",
  "tipo_dte": "01",
  "numero_control": "DTE-01-M001P001-000000000000135",
  "codigo_generacion": "AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA"
}
```

El job descarga desde `dte-core`:

- `GET /api/v1/dte/drafts/{id}/artifacts/pdf`
- `GET /api/v1/dte/drafts/{id}/artifacts/client-json`

Luego guarda adjuntos, envia el correo y registra eventos `queued`, `processing`, `sent` o `failed`.

## Variables

```dotenv
NOTIFICATIONS_API_TOKEN=
NOTIFICATIONS_PROVIDER="${MAIL_MAILER}"
NOTIFICATIONS_ATTACHMENTS_DISK=local
NOTIFICATIONS_ATTACHMENTS_PATH=notifications

DTE_CORE_BASE_URL=http://127.0.0.1/api/v1
DTE_CORE_TOKEN=
DTE_CORE_TIMEOUT=20
```

## Verificacion

```bash
php artisan test
vendor/bin/pint --test
composer validate --no-check-publish
```
