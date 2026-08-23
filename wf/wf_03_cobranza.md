# Especificación ArquITECTÓNICA DEL WORKFLOW 03: GENERACIÓN Y DESPACHO AUTOMATIZADO DE COBRANZA POR WHATSAPP
**Documento:** `wf_03_cobranza.md`  
**Proyecto:** Automatización de Cobranza y Control de Despachos — Distribuidora de Agua Mineral  
**Estado:** Producción / Auditado con Excepciones Operativas  
**Versión:** 1.0.0  

---

## 1. RESUMEN EJECUTIVO Y OBJETIVOS

El **Workflow 03 (WF-03)** es la capa de interacción y comunicación con el cliente final. Recibe la `cola_cobranza.json` procesada por el WF-02 y se encarga de construir, personalizar, programar y despachar los mensajes de cobranza preventiva a través de la API de WhatsApp.

### Objetivos Clave
1. **Adherencia Estricta al Formato Institucional:** Garantizar que los mensajes mantengan exactamente el tono cálido, educado y la estructura preaprobada por la dirección de la distribuidora.
2. **Prohibición Expresa de Montos Financieros Directos:** Respetar la regla de negocio que prohíbe taxativamente mostrar montos o importes en dólares en los mensajes automáticos cotidianos, limitándose exclusivamente al conteo numérico de botellas.
3. **Manejo Inteligente de Marcas Múltiples:** Formatear de forma transparente las entregas cuando un cliente recibe o adecua simultáneamente productos **La Zenda** ($7 USD) y **Los Alpes** ($3 USD).
4. **Protección Anti-Bloqueo de Línea (Rate-Limiting & Anti-Spam):** Implementar políticas de espaciado temporal (jitter aleatorio de 15 a 45 segundos entre envíos) y franjas horarias seguras para evitar baneos o restricciones por parte de Meta/WhatsApp.
5. **Auditoría de Envíos y Trazabilidad:** Producir el archivo `log_envios_cobranza.json` y actualizar el estado de los despachos en la base de datos a `notificado`.

---

## 2. ARQUITECTURA DEL PIPELINE WF-03

```
+-----------------------------------------------------------------------------------+
|                        PIPELINE WF-03: COBRANZA AUTOMATIZADA WHATSAPP             |
|                                                                                   |
|  [ cola_cobranza.json (WF-02) ]                                                   |
|                │                                                                  |
|                ▼                                                                  |
|  ┌──────────────────────────────┐                                                 |
|  │  1. Programador de Cola      │                                                 |
|  │  (Horario & Rate Limiter)    │                                                 |
|  └─────────────┬────────────────┘                                                 |
|                │                                                                  |
|                ▼                                                                  |
|  ┌──────────────────────────────┐                                                 |
|  │  2. Generador de Plantillas  │                                                 |
|  │  (Construcción Dinámica)     │                                                 |
|  └─────────────┬────────────────┘                                                 |
|                │                                                                  |
|                ▼                                                                  |
|  ┌──────────────────────────────┐                                                 |
|  │  3. Gateway API WhatsApp     │ ──> [ Envío al Cliente por WhatsApp ]             |
|  │  (Meta API / UltraMsg/Baileys)│                                                 |
|  └─────────────┬────────────────┘                                                 |
|                │                                                                  |
|                ▼                                                                  |
|  ┌──────────────────────────────┐                                                 |
|  │  4. Auditoría y Registro     │                                                 |
|  │  (Actualización BD & Logs)   │                                                 |
|  └─────────────┬────────────────┘                                                 |
|                │                                                                  |
|                ▼                                                                  |
|     [ log_envios_cobranza.json ]                                                  |
+-----------------------------------------------------------------------------------+
```

---

## 3. PLANTILLA OFICIAL Y REGLAS DE CONSTRUCCIÓN DINÁMICA

### 3.1 Estructura Base de la Plantilla de WhatsApp

```text
Hola buen día estimado cliente 🤗
Espero se encuentre muy bien.

Para confirmar la recepción de {texto_despacho_hoy} del día {dia_semana} {fecha_formateada}.
{bloque_pendiente_acumulado}

Si ya fueron canceladas, por favor notificar.

Muchísimas gracias por su colaboración.
Feliz y bendecido día🙏
```

---

### 3.2 Lógica de Formateo de Botellas y Marcas (Zenda vs Alpes)

El generador de mensajes evalúa las cantidades entregadas y acumuladas para construir los textos dinámicos `{texto_despacho_hoy}` y `{bloque_pendiente_acumulado}` según la marca del producto:

#### Regla A: Cliente de Una Sola Marca (Solo Los Alpes o Solo La Zenda)
* Si solo maneja botellas **Los Alpes** (Agua de pozo $3):
  * Ej. 2 hoy, 1 pendiente:
    * `{texto_despacho_hoy}` = `2 botella(s)`
    * `{bloque_pendiente_acumulado}` = `Tenía 1 botella(s) pendiente(s) de entregas anteriores.`
* Si solo maneja botellas **La Zenda** (Agua de manantial $7):
  * Ej. 1 hoy, 2 pendientes:
    * `{texto_despacho_hoy}` = `1 botella(s) La Zenda`
    * `{bloque_pendiente_acumulado}` = `Tenía 2 botella(s) La Zenda pendiente(s) de entregas anteriores.`

#### Regla B: Cliente Mixto (Combina Zenda y Alpes)
* Ej. 2 Alpes + 1 Zenda hoy, y 3 Alpes pendientes:
  * `{texto_despacho_hoy}` = `3 botella(s) (2 Alpes / 1 Zenda)`
  * `{bloque_pendiente_acumulado}` = `Tenía 3 botella(s) Alpes pendiente(s) de entregas anteriores.`

#### Regla C: Cliente Inactivo (Sin despacho hoy, pero con saldo deudor)
* Se ajusta ligeramente el saludo inicial:
```text
Hola buen día estimado cliente 🤗
Espero se encuentre muy bien.

Le escribimos para recordarle que mantiene un saldo pendiente de {bloque_pendiente_acumulado_inactivo}.

Si ya fue cancelado, por favor notificar.

Muchísimas gracias por su colaboración.
Feliz y bendecido día🙏
```

---

## 4. POLÍTICAS DE RATE-LIMITING, ANTISPAM Y HORARIOS DE DISPARO

Para proteger la cuenta telefónica de la distribuidora contra bloqueos automatizados por parte de WhatsApp/Meta, se aplican las siguientes reglas técnicas obligatorias:

1. **Jitter Aleatorio Inter-Mensaje:**
   * Entre el envío de un mensaje y el siguiente, el worker debe esperar un tiempo de pausa aleatorio comprendido entre **18 y 42 segundos**.
   * `pausa = random.randint(18, 42)`

2. **Límite de Ráfaga por Lote:**
   * Tras enviar un bloque de **15 mensajes**, el sistema aplica una pausa extendida de **5 minutos (300 segundos)** para simular un comportamiento humano natural.

3. **Ventana Horaria Permitida:**
   * Los despachos de mensajes se ejecutarán exclusivamente en el rango de **08:30 AM a 06:00 PM** (Hora de Caracas, VET / UTC-4).
   * Si la conciliación del WF-02 concluye fuera de este horario (ej. 7:30 PM), los mensajes quedan en estado `en_espera_cola` para ser disparados automáticamente a las 08:30 AM del día siguiente.

4. **Tratamiento de Reintentos (Retry Policy):**
   * En caso de error de conexión o fallo de entrega (HTTP 500 / Timeout), el mensaje se reintentará máximo **3 veces** con exponenciación de espera (1 min, 5 min, 15 min). Si falla la 3ª vez, pasa a `estado_envio: "fallido_tecnico"` en los registros de auditoría.

---

## 5. ESQUEMA JSON DE SALIDA (`log_envios_cobranza.json`)

```json
{
  "session_id": "WF03-20260726-001",
  "fecha_ejecucion": "2026-07-26T10:15:30-04:00",
  "total_mensajes_procesados": 2,
  "mensajes_exitosos": 2,
  "mensajes_fallidos": 0,
  "registros_envio": [
    {
      "cliente_id": 104,
      "nombre_oficial": "Pastelería Chacao C.A.",
      "telefono_whatsapp": "+584121234567",
      "timestamp_envio": "2026-07-26T10:15:32-04:00",
      "estado_envio": "enviado",
      "id_mensaje_whatsapp": "wamid.HBgLNTg0MTIxMjM0NTY3FQIAERgSQTU2Nzg5MDEyMzQ1Njc4OQA=",
      "mensaje_texto_enviado": "Hola buen día estimado cliente 🤗
Espero se encuentre muy bien.

Para confirmar la recepción de 3 botella(s) La Zenda del día Domingo 26/07/2026.
Tenía 2 botella(s) La Zenda y 1 botella(s) Alpes pendiente(s) de entregas anteriores.

Si ya fueron canceladas, por favor notificar.

Muchísimas gracias por su colaboración.
Feliz y bendecido día🙏",
      "reintentos_realizados": 0
    },
    {
      "cliente_id": 88,
      "nombre_oficial": "Residencias Tucurabua Apt 3-05",
      "telefono_whatsapp": "+584149876543",
      "timestamp_envio": "2026-07-26T10:16:05-04:00",
      "estado_envio": "enviado",
      "id_mensaje_whatsapp": "wamid.HBgLNTg0MTQ5ODc2NTQzFQIAERgSQTU2Nzg5MDEyMzQ1Njc4OQB=",
      "mensaje_texto_enviado": "Hola buen día estimado cliente 🤗
Espero se encuentre muy bien.

Le escribimos para recordarle que mantiene un saldo pendiente de 4 botella(s) Alpes de entregas anteriores.

Si ya fue cancelado, por favor notificar.

Muchísimas gracias por su colaboración.
Feliz y bendecido día🙏",
      "reintentos_realizados": 0
    }
  ]
}
```

---

## 6. ACTUALIZACIÓN EN BASE DE DATOS (EFECTO SECUNDARIO DEL WF-03)

Una vez confirmado el recibo por parte de la API de WhatsApp (`estado_envio: "enviado"`), el trabajador de segundo plano ejecuta la siguiente actualización SQL en la base de datos:

```sql
-- Actualiza el estado del despacho a 'notificado' con la estampa de tiempo
UPDATE despachos
SET estado_pago = 'notificado',
    observaciones = COALESCE(observaciones, '') || ' | Cobranza enviada por WA el ' || CURRENT_TIMESTAMP
WHERE cliente_id = :cliente_id 
  AND fecha = :fecha_actual;
```

---

## 7. SYSTEM PROMPT DE EJECUCIÓN PARA EL AGENTE DE GENERACIÓN DE MENSAJES

```text
Eres el Agente Especialista en Generación y Envío de Cobranza por WhatsApp para la Distribuidora de Agua Mineral.
Tu tarea es tomar los objetos de 'cola_cobranza.json' y construir los mensajes formateados según las pautas institucionales.

REGLAS DE GENERACIÓN DE MENSAJES:

1. NUNCA INCLUYAS MONTOS EN DÓLARES ($) NI EN BOLÍVARES (Bs) EN EL TEXTO DEL MENSAJE.
2. Utiliza estrictamente la plantilla predeterminada:
   Hola buen día estimado cliente 🤗
   Espero se encuentre muy bien.

   Para confirmar la recepción de {texto_despacho_hoy} del día {dia_semana} {fecha}.
   {bloque_pendiente_acumulado}

   Si ya fueron canceladas, por favor notificar.

   Muchísimas gracias por su colaboración.
   Feliz y bendecido día🙏

3. REGLAS DE TEXTO PARA BOTELLAS:
   - Si las botellas son de La Zenda, acompáñalas con la palabra "La Zenda".
   - Si las botellas son Alpes, puedes usar "botella(s)" o "botella(s) Alpes".
   - Si no hay despacho hoy, usa la variante de plantilla para clientes inactivos con saldo pendiente.

4. Devuelve la lista estructurada en 'log_envios_cobranza.json'.
```

---

## 8. CONCLUSIÓN Y SIGUIENTE PASO

El **Workflow 03** garantiza una comunicación fluida, cortés y estandarizada con los clientes, maximizando la tasa de respuesta y recupero de cartera sin poner en riesgo la reputación comercial de la empresa ni la línea telefónica operativa.

Con este documento completo, el sistema está listo para definir el **Workflow 04 (Gestión de Pagos, Conciliación Manual y Auditoría)**.
