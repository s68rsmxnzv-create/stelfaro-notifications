<x-mail::message>
# Documento tributario electronico

Hola {{ $message->recipient_name ?: 'cliente' }},

Adjuntamos el comprobante emitido y su JSON fiscal firmado.

Este correo fue generado automaticamente. Por favor no respondas a este mensaje.

@if(! empty($metadata['numero_control']))
**Numero de control:** {{ $metadata['numero_control'] }}
@endif

@if(! empty($metadata['codigo_generacion']))
**Codigo de generacion:** {{ $metadata['codigo_generacion'] }}
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
