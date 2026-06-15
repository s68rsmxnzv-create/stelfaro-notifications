<x-mail::message>
# Documento tributario electronico

Hola {{ $message->recipient_name ?: 'cliente' }},

Adjuntamos el comprobante emitido y su JSON fiscal firmado.

@if(! empty($metadata['numero_control']))
**Numero de control:** {{ $metadata['numero_control'] }}
@endif

@if(! empty($metadata['codigo_generacion']))
**Codigo de generacion:** {{ $metadata['codigo_generacion'] }}
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
