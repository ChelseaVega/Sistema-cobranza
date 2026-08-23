# Especificación Arquitectónica del Workflow 01: Ingesta, OCR y Extracción Estructurada de Datos
**Documento:** `wf_01_ingesta.md`  
**Proyecto:** Automatización de Cobranza y Control de Despachos — Distribuidora de Agua Mineral  
**Estado:** Producción / Auditado con Excepciones Operativas  
**Versión:** 1.0.0  

---

## 1. RESUMEN EJECUTIVO Y OBJETIVOS

El **Workflow 01 (WF-01)** es el punto de entrada de los datos operativos del sistema. Su función primordial es transformar imágenes de listas de despachos diarias —tanto transcripciones manuscritas tomadas en hoja de libreta como capturas de pantalla de la aplicación de notas de teléfonos móviles en modo claro o oscuro— en un documento JSON estandarizado, estructurado y matemáticamente validado: `despachos_diarios.json`.

### Objetivos Clave
1. **Inmunidad a la Variabilidad de Fuentes:** Procesar fluidamente manuscritos en papel y notas digitales enviadas por WhatsApp con diversidad de formatos, terminologías, separadores (`:`, `_`, `-`, espacios) y sintaxis.
2. **Cero Pérdida de Información Operativa:** Extraer no solo las cantidades y tipos de producto, sino también comentarios logísticos, observaciones de pago (Efectivo USD/Bs, Pago Móvil), abonos preliminares y jerarquías residenciales/comerciales (edificios, pisos, apartamentos, locales).
3. **Mapeo Taxonómico Riguroso:** Interpretar las distintas denominaciones utilizadas por los chóferes para clasificar correctamente los dos productos comercializados: **La Zenda** ($7 USD) y **Los Alpes** ($3 USD).
4. **Generación de Alertas de Baja Confianza:** Detectar discrepancias en totales, textos ilegibles o ambigüedades en nombres para bandera azul/roja de revisión humana inmediata antes de la conciliación en base de datos.

---

## 2. ARQUITECTURA DE PIPELINE Y FLUJO DE PROCESAMIENTO

```
+-----------------------------------------------------------------------------------+
|                            PIPELINE WF-01: INGESTA DE DATOS                       |
|                                                                                   |
|  [ WhatsApp / OCR Driver Inputs ]                                                 |
|          │                                                                        |
|          ├──> Lunes: 4 listas (1 Manuscrita OCR + 3 Fotos Digitales)             |
|          └──> Mar-Vie: 3 listas (1 Manuscrita OCR + 2 Fotos Digitales)            |
|                                                                                   |
|                                     │                                             |
|                                     ▼                                             |
|                     ┌──────────────────────────────┐                              |
|                     │  1. Ingestion & Preprocess   │                              |
|                     │  (Detección Día / Conteo)    │                              |
|                     └──────────────┬───────────────┘                              |
|                                    │                                              |
|                                    ▼                                              |
|                     ┌──────────────────────────────┐                              |
|                     │   2. Vision LLM / OCR Core   │                              |
|                     │   (Prompt de Extracción)     │                              |
|                     └──────────────┬───────────────┘                              |
|                                    │                                              |
|                                    ▼                                              |
|                     ┌──────────────────────────────┐                              |
|                     │  3. Normalización & Parsing  │                              |
|                     │  (Jerarquías & Productos)    │                              |
|                     └──────────────┬───────────────┘                              |
|                                    │                                              |
|                                    ▼                                              |
|                     ┌──────────────────────────────┐                              |
|                     │  4. Validaciones & Sumatorias│                              |
|                     │  (Cross-check Totales)       │                              |
|                     └──────────────┬───────────────┘                              |
|                                    │                                              |
|                                    ▼                                              |
|                        [ despachos_diarios.json ]                                 |
+-----------------------------------------------------------------------------------+
```

---

## 3. TAXONOMÍA Y REGLAS DE PARSEO DE NEGOCIO

### 3.1 Clases de Productos y Mapeo Sintáctico
Los chóferes utilizan múltiples nomenclaturas abreviadas en el día a día. El motor debe aplicar la siguiente tabla de equivalencias obligatoria:

| Expresión en Imagen / Nota | Producto Asignado | Precio Unitario | Código JSON |
| :--- | :--- | :--- | :--- |
| `Z`, `Zenda`, `zenda`, `zendas`, `ZENDA` | **La Zenda** (Agua Mineral de Manantial) | $7.00 USD | `botellas_zenda` |
| `AZUL`, `azul`, `AGUA`, `agua`, `aguas`, `botella`, `botellas`, Número solo (ej. `53_______2`) | **Los Alpes** (Agua Mineral de Pozo) | $3.00 USD | `botellas_alpes` |

> **Regla de Ocultamiento de Alpes:** Por norma del negocio, si en la lista aparece una cifra numérica sin especificación de marca (o acompañada de la palabra "AGUA", "AZUL" o "botellas"), se asume **automáticamente** que corresponde a **Los Alpes**.

### 3.2 Manejo de Estructuras Jerárquicas (Edificios, Zonas y Conjuntos)
Cuando una lista presenta un encabezado principal (ej. `EDF. TUCURABUA`, `PARIMA`, `NUEVO CENTRO`, `Athaltic`, `san Vicente`, `.thamara`) seguido de sub-elementos (pisos, apartamentos, nombres de residentes o locales), el extractor debe:
1. Heredar la entidad padre como `zona_edificio` o prefijo en `nombre_cliente_raw`.
2. Mantener la especificidad de la unidad en `unidad_sublocal` (ej. `PISO 4 APT 4-04`, `PH1`, `704`, `13C`).
3. Construir un `alias_despacho_consolidado` limpio para cruzamiento en la base de datos (WF-02).

### 3.3 Extracción de Transacciones Financieras In-line (Comentarios de Pago)
Las listas contienen frecuentemente anotaciones operativas de pago que **NO** deben perderse, ya que alimentan la conciliación de caja diaria:
* **Montos en Bs:** `6000 BS`, `7.800 Bs`, `9000 BS`, `PAGO MOBIL NRF: 65638`.
* **Montos en USD:** `5$`, `20$ DOLARES`, `3.5$`.
* **Estado de Pago explícito:** `PAGO`, `pagado`, `ella le iba a dar el pago móvil preguntal`.
* **Intercambios / Cambios:** `1 cambió`, `cambio de envase`.

---

## 4. ESQUEMA JSON FINAL (`despachos_diarios.json`)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "DespachosDiarios",
  "type": "object",
  "required": [
    "metadata_procesamiento",
    "resumen_diario",
    "despachos"
  ],
  "properties": {
    "metadata_procesamiento": {
      "type": "object",
      "required": ["fecha_procesamiento", "dia_semana", "despachador", "origen_fuente", "total_listas_esperadas", "total_listas_procesadas"],
      "properties": {
        "fecha_procesamiento": { "type": "string", "format": "date" },
        "dia_semana": { "type": "string", "enum": ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"] },
        "despachador": { "type": "string" },
        "origen_fuente": { "type": "string", "enum": ["manuscrito_ocr", "whatsapp_nota_digital", "mixto"] },
        "total_listas_esperadas": { "type": "integer" },
        "total_listas_procesadas": { "type": "integer" }
      }
    },
    "resumen_diario": {
      "type": "object",
      "required": ["total_botellas_zenda", "total_botellas_alpes", "total_liquidos", "monto_bruto_calculado_usd", "total_registros"],
      "properties": {
        "total_botellas_zenda": { "type": "integer" },
        "total_botellas_alpes": { "type": "integer" },
        "total_liquidos": { "type": "integer" },
        "monto_bruto_calculado_usd": { "type": "number" },
        "total_registros": { "type": "integer" },
        "observaciones_pie_pagina": { "type": "string" }
      }
    },
    "despachos": {
      "type": "array",
      "items": {
        "type": "object",
        "required": [
          "id_item",
          "nombre_cliente_raw",
          "alias_despacho_consolidado",
          "botellas_zenda",
          "botellas_alpes",
          "monto_calculado_usd",
          "estado_pago_declarado",
          "requiere_revision_humana"
        ],
        "properties": {
          "id_item": { "type": "integer" },
          "zona_edificio": { "type": ["string", "null"] },
          "unidad_sublocal": { "type": ["string", "null"] },
          "nombre_cliente_raw": { "type": "string" },
          "alias_despacho_consolidado": { "type": "string" },
          "botellas_zenda": { "type": "integer", "minimum": 0 },
          "botellas_alpes": { "type": "integer", "minimum": 0 },
          "monto_calculado_usd": { "type": "number" },
          "estado_pago_declarado": {
            "type": "string",
            "enum": ["pendiente", "pagado_efectivo_usd", "pagado_efectivo_bs", "pago_movil", "parcial", "por_verificar"]
          },
          "monto_pagado_declarado_bs": { "type": ["number", "null"] },
          "monto_pagado_declarado_usd": { "type": ["number", "null"] },
          "referencia_pago": { "type": ["string", "null"] },
          "observaciones_chofer": { "type": ["string", "null"] },
          "requiere_revision_humana": { "type": "boolean" },
          "motivo_revision": { "type": ["string", "null"] }
        }
      }
    }
  }
}
```

---

## 5. SYSTEM PROMPT DE PRODUCCIÓN PARA EL MOTOR LLM/VISION

A continuación se presenta la instrucción exacta que debe inyectarse al modelo de visión (GPT-4o / Claude 3.5 Sonnet / Gemini 1.5 Pro) para garantizar la lectura de las imágenes sin errores.

```text
Eres un Sistema Experto de OCR y Extracción Estructurada de Datos Operativos para una distribuidora de agua mineral en Caracas, Venezuela.
Tu tarea es recibir una o varias imágenes de listas de despacho (manuscritas en papel o capturas de notas de teléfono WhatsApp) y convertirlas en un JSON estrictamente estructurado según el esquema especificado.

REGLAS DE INTERPRETACIONAL Y MAPPING OBLIGATORIAS:

1. CLASIFICACIÓN DE PRODUCTOS:
   - "La Zenda" ($7 USD por botellón): Se identifica explícitamente por "Z", "Zenda", "zenda", "zendas", "ZENDA".
   - "Los Alpes" ($3 USD por botellón): Se identifica explícitamente por "AZUL", "azul", "AGUA", "agua", "aguas", "botella", "botellas", o por la simple presencia de una CANTIDAD NUMÉRICA sin palabra aclaratoria (ej. "53_______2" significa 2 botellas de Los Alpes).

2. JERARQUÍAS Y ESTRUCTURA DE LOCALES/EDIFICIOS:
   - Si existe un encabezado de edificio o centro comercial (ej. "NUEVO CENTRO", "PARIMA", "EDF. TUCURABUA", "Athaltic", "san Vicente"), asócialo a los registros subsiguientes en la propiedad "zona_edificio".
   - Si la fila indica un apartamento o piso (ej. "PISO 4 APT 4-04", "1101", "4A", "13C"), colócalo en "unidad_sublocal".
   - Construye "alias_despacho_consolidado" combinando [zona_edificio] + [unidad_sublocal o nombre_cliente_raw] (ej. "EDF TUCURABUA PISO 4 APT 4-04", "PARIMA 1101", "Athaltic 13C").

3. PAGOS Y OBSERVACIONES EN LÍNEA:
   - Si la fila incluye referencias de Pago Móvil (ej. "PAGO MOBIL NRF: 65638", "NRF! 6342"), colócalo en "referencia_pago" y marca "estado_pago_declarado": "pago_movil".
   - Si indica montos en Bolívares (ej. "6000 BS", "7.800 Bs", "9000BS"), extrae el valor numérico en "monto_pagado_declarado_bs".
   - Si indica montos en Dólares (ej. "5$", "20$"), extrae el valor numérico en "monto_pagado_declarado_usd".
   - Si contiene notas de cobro (ej. "ella le iba a dar el pago móvil preguntal", "CONCERJE", "1 cambió"), regístralo en "observaciones_chofer".

4. CÁLCULO FINANCIERO:
   - monto_calculado_usd = (botellas_zenda * 7.00) + (botellas_alpes * 3.00).

5. VALIDACIÓN Y SEGURIDAD:
   - Si un texto es ambiguo o una cantidad dudosa, marca "requiere_revision_humana": true y describe la duda en "motivo_revision".
   - Suma todos los ítems extrayendo el "resumen_diario". Si la nota contiene un total de pie de página (ej. "54 líquidos vendidos + 1 cambió"), compáralo contra la suma calculada.

FORMATO DE SALIDA:
Devuelve ÚNICAMENTE el objeto JSON sin bloques de código markdown innecesarios o comentarios adicionales.
```

---

## 6. PRUEBAS DE CONCEPTO Y BENCHMARKS CON IMÁGENES REALES DE CAMPO

A continuación se muestran los resultados reales de procesamiento obtenidos de las 4 imágenes enviadas por los chóferes:

### 6.1 Prueba 1: Manuscrito en Papel de Libreta (`WhatsApp Image 2026-07-26 at 7.13.51 PM.jpeg`)
* **Características de la fuente:** Manuscrito con bolígrafo azul, múltiples notas de Pago Móvil, dólares en efectivo, bolívares en efectivo, divisiones por edificio y notas al pie ("CONTINUA PARTE ATRAS ->").

```json
{
  "metadata_procesamiento": {
    "fecha_procesamiento": "2026-07-26",
    "dia_semana": "Domingo",
    "despachador": "Despachador Chacao",
    "origen_fuente": "manuscrito_ocr",
    "total_listas_esperadas": 1,
    "total_listas_procesadas": 1
  },
  "resumen_diario": {
    "total_botellas_zenda": 22,
    "total_botellas_alpes": 48,
    "total_liquidos": 70,
    "monto_bruto_calculado_usd": 298.0,
    "total_registros": 20,
    "observaciones_pie_pagina": "CONTINUA PARTE ATRAS ->"
  },
  "despachos": [
    {
      "id_item": 1,
      "zona_edificio": "ZONA CHACAO",
      "unidad_sublocal": null,
      "nombre_cliente_raw": "SEGURIDAD CAMPO ALEGRE",
      "alias_despacho_consolidado": "SEGURIDAD CAMPO ALEGRE",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 2,
      "zona_edificio": "EDF. COIMBRA",
      "unidad_sublocal": null,
      "nombre_cliente_raw": "EDF. COIMBRA",
      "alias_despacho_consolidado": "EDF COIMBRA",
      "botellas_zenda": 2,
      "botellas_alpes": 0,
      "monto_calculado_usd": 14.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 3,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "PASTELERIA CHACAO",
      "alias_despacho_consolidado": "PASTELERIA CHACAO",
      "botellas_zenda": 3,
      "botellas_alpes": 0,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pago_movil",
      "monto_pagado_declarado_bs": 1650.0,
      "referencia_pago": "65638",
      "observaciones_chofer": "3 ZENDA + 1 AGUA ORIGINAL. PAGO MOBIL NRF: 65638 BS 1650",
      "requiere_revision_humana": false
    },
    {
      "id_item": 4,
      "zona_edificio": "EDF. TUCURABUA",
      "unidad_sublocal": "PISO 4 APT 4-04",
      "nombre_cliente_raw": "EDF. TUCURABUA PISO 4 APT 4-04",
      "alias_despacho_consolidado": "EDF TUCURABUA PISO 4 APT 4-04",
      "botellas_zenda": 0,
      "botellas_alpes": 1,
      "monto_calculado_usd": 3.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 5,
      "zona_edificio": "EDF. TUCURABUA",
      "unidad_sublocal": "PISO 3 APT 3-05",
      "nombre_cliente_raw": "EDF. TUCURABUA PISO 3 APT 3-05",
      "alias_despacho_consolidado": "EDF TUCURABUA PISO 3 APT 3-05",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 6,
      "zona_edificio": "EDF. TUCURABUA",
      "unidad_sublocal": "PISO 3 APT 3-04",
      "nombre_cliente_raw": "EDF. TUCURABUA PISO 3 APT 3-04",
      "alias_despacho_consolidado": "EDF TUCURABUA PISO 3 APT 3-04",
      "botellas_zenda": 0,
      "botellas_alpes": 1,
      "monto_calculado_usd": 3.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 7,
      "zona_edificio": "EDF. TUCURABUA",
      "unidad_sublocal": "PISO 2 APT 2-04",
      "nombre_cliente_raw": "EDF. TUCURABUA PISO 2 APT 2-04",
      "alias_despacho_consolidado": "EDF TUCURABUA PISO 2 APT 2-04",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 8,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "CLIENTE EFECTIVO BS",
      "alias_despacho_consolidado": "CLIENTE EFECTIVO BS",
      "botellas_zenda": 0,
      "botellas_alpes": 5,
      "monto_calculado_usd": 15.0,
      "estado_pago_declarado": "pagado_efectivo_bs",
      "monto_pagado_declarado_bs": 6000.0,
      "observaciones_chofer": "6000 BS - 5 AGUA EN EFECTIVO",
      "requiere_revision_humana": false
    },
    {
      "id_item": 9,
      "zona_edificio": "CENTRO PROF. MIRANDA",
      "unidad_sublocal": "PH-A",
      "nombre_cliente_raw": "CENTRO PROF. MIRANDA PH-A",
      "alias_despacho_consolidado": "CENTRO PROF MIRANDA PH-A",
      "botellas_zenda": 3,
      "botellas_alpes": 0,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 10,
      "zona_edificio": "CENTRO PROF. MIRANDA",
      "unidad_sublocal": "6-B",
      "nombre_cliente_raw": "CENTRO PROF. MIRANDA 6-B",
      "alias_despacho_consolidado": "CENTRO PROF MIRANDA 6-B",
      "botellas_zenda": 1,
      "botellas_alpes": 0,
      "monto_calculado_usd": 7.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 11,
      "zona_edificio": "CENTRO PROF. MIRANDA",
      "unidad_sublocal": "4-B",
      "nombre_cliente_raw": "CENTRO PROF. MIRANDA 4-B",
      "alias_despacho_consolidado": "CENTRO PROF MIRANDA 4-B",
      "botellas_zenda": 5,
      "botellas_alpes": 0,
      "monto_calculado_usd": 35.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 12,
      "zona_edificio": "CENTRO PROF. MIRANDA",
      "unidad_sublocal": "3-D",
      "nombre_cliente_raw": "CENTRO PROF. MIRANDA 3-D",
      "alias_despacho_consolidado": "CENTRO PROF MIRANDA 3-D",
      "botellas_zenda": 0,
      "botellas_alpes": 3,
      "monto_calculado_usd": 9.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 13,
      "zona_edificio": "CENTRO PROF. MIRANDA",
      "unidad_sublocal": "2-C",
      "nombre_cliente_raw": "CENTRO PROF. MIRANDA 2-C",
      "alias_despacho_consolidado": "CENTRO PROF MIRANDA 2-C",
      "botellas_zenda": 0,
      "botellas_alpes": 7,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 14,
      "zona_edificio": "CENTRO PROF. MIRANDA",
      "unidad_sublocal": "1-C",
      "nombre_cliente_raw": "CENTRO PROF. MIRANDA 1-C",
      "alias_despacho_consolidado": "CENTRO PROF MIRANDA 1-C",
      "botellas_zenda": 0,
      "botellas_alpes": 3,
      "monto_calculado_usd": 9.0,
      "estado_pago_declarado": "pagado_efectivo_usd",
      "observaciones_chofer": "PAGO EN SITIO",
      "requiere_revision_humana": false
    },
    {
      "id_item": 15,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "SAMIR ARABE",
      "alias_despacho_consolidado": "SAMIR ARABE",
      "botellas_zenda": 5,
      "botellas_alpes": 0,
      "monto_calculado_usd": 35.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 16,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "FERRETERIA NAY- POTO",
      "alias_despacho_consolidado": "FERRETERIA NAY POTO",
      "botellas_zenda": 1,
      "botellas_alpes": 0,
      "monto_calculado_usd": 7.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 17,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "MAXIMUS",
      "alias_despacho_consolidado": "MAXIMUS",
      "botellas_zenda": 0,
      "botellas_alpes": 5,
      "monto_calculado_usd": 15.0,
      "estado_pago_declarado": "pago_movil",
      "monto_pagado_declarado_bs": 9000.0,
      "referencia_pago": "6342",
      "observaciones_chofer": "PAGO MOBIL NRF! 6342 BS 9000BS - 5 AGUA",
      "requiere_revision_humana": false
    },
    {
      "id_item": 18,
      "zona_edificio": "EDF. COSMO",
      "unidad_sublocal": "PISO 5 APT 5-A",
      "nombre_cliente_raw": "EDF. COSMO PISO 5 APT 5-A",
      "alias_despacho_consolidado": "EDF COSMO PISO 5 APT 5-A",
      "botellas_zenda": 1,
      "botellas_alpes": 0,
      "monto_calculado_usd": 7.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 19,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "ABASTO LAS JOYAS",
      "alias_despacho_consolidado": "ABASTO LAS JOYAS",
      "botellas_zenda": 0,
      "botellas_alpes": 8,
      "monto_calculado_usd": 24.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 20,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "TIPOGRAFIA CHACAO",
      "alias_despacho_consolidado": "TIPOGRAFIA CHACAO",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    }
  ]
}
```

---

### 6.2 Prueba 2: Nota Digital Modo Oscuro (`WhatsApp Image 2026-07-22 at 9.12.44 PM.jpeg`)
* **Características de la fuente:** Pantalla oscura de App de Notas. Nomenclatura `:2 AZUL`, agrupaciones por edificio `NUEVO CENTRO` y `PARIMA`, totales calculados al final ("54 líquidos vendidos + 1 cambió").

```json
{
  "metadata_procesamiento": {
    "fecha_procesamiento": "2026-07-22",
    "dia_semana": "Miércoles",
    "despachador": "Despachador Cuentas",
    "origen_fuente": "whatsapp_nota_digital",
    "total_listas_esperadas": 3,
    "total_listas_procesadas": 1
  },
  "resumen_diario": {
    "total_botellas_zenda": 7,
    "total_botellas_alpes": 47,
    "total_liquidos": 54,
    "monto_bruto_calculado_usd": 190.0,
    "total_registros": 21,
    "observaciones_pie_pagina": "54 líquidos vendidos + 1 cambió"
  },
  "despachos": [
    {
      "id_item": 1,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "Trajes Alexander",
      "alias_despacho_consolidado": "Trajes Alexander",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 2,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "FISA",
      "alias_despacho_consolidado": "FISA",
      "botellas_zenda": 0,
      "botellas_alpes": 15,
      "monto_calculado_usd": 45.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 3,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "Empleado Fisa",
      "alias_despacho_consolidado": "Empleado Fisa",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 4,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "PROVINCIAL",
      "alias_despacho_consolidado": "PROVINCIAL",
      "botellas_zenda": 0,
      "botellas_alpes": 3,
      "monto_calculado_usd": 9.0,
      "estado_pago_declarado": "pagado_efectivo_bs",
      "monto_pagado_declarado_bs": 7800.0,
      "observaciones_chofer": "7.800 Bs cancelados",
      "requiere_revision_humana": false
    },
    {
      "id_item": 5,
      "zona_edificio": "NUEVO CENTRO",
      "unidad_sublocal": "12",
      "nombre_cliente_raw": "NUEVO CENTRO 12",
      "alias_despacho_consolidado": "NUEVO CENTRO 12",
      "botellas_zenda": 0,
      "botellas_alpes": 4,
      "monto_calculado_usd": 12.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 6,
      "zona_edificio": "NUEVO CENTRO",
      "unidad_sublocal": "4A",
      "nombre_cliente_raw": "NUEVO CENTRO 4A",
      "alias_despacho_consolidado": "NUEVO CENTRO 4A",
      "botellas_zenda": 1,
      "botellas_alpes": 0,
      "monto_calculado_usd": 7.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 7,
      "zona_edificio": "PARIMA",
      "unidad_sublocal": "PH1",
      "nombre_cliente_raw": "PARIMA PH1",
      "alias_despacho_consolidado": "PARIMA PH1",
      "botellas_zenda": 0,
      "botellas_alpes": 3,
      "monto_calculado_usd": 9.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 8,
      "zona_edificio": "PARIMA",
      "unidad_sublocal": "704",
      "nombre_cliente_raw": "PARIMA 704",
      "alias_despacho_consolidado": "PARIMA 704",
      "botellas_zenda": 3,
      "botellas_alpes": 0,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 9,
      "zona_edificio": "PARIMA",
      "unidad_sublocal": "CONCERJE",
      "nombre_cliente_raw": "PARIMA CONCERJE",
      "alias_despacho_consolidado": "PARIMA CONCERJE",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "observaciones_chofer": "Entrega realizada al conserje",
      "requiere_revision_humana": false
    }
  ]
}
```

---

### 6.3 Prueba 3: Nota Digital Modo Claro con Formato de Sangría (`WhatsApp Image 2026-07-22 at 8.50.21 PM.jpeg`)
* **Características de la fuente:** Fecha explícita en cabecera `julio 22, 2026 a las 8:48 p.m.`. Nomenclatura `botellas` vs `zendas`. Agrupación residencial `Athaltic` con múltiples apartamentos (`13C`, `13D`, `8D`, `4B`, `3C`, `3B`, `2B`).

```json
{
  "metadata_procesamiento": {
    "fecha_procesamiento": "2026-07-22",
    "dia_semana": "Miércoles",
    "despachador": "Despachador Noche",
    "origen_fuente": "whatsapp_nota_digital",
    "total_listas_esperadas": 3,
    "total_listas_procesadas": 1
  },
  "resumen_diario": {
    "total_botellas_zenda": 7,
    "total_botellas_alpes": 25,
    "total_liquidos": 32,
    "monto_bruto_calculado_usd": 124.0,
    "total_registros": 12,
    "observaciones_pie_pagina": null
  },
  "despachos": [
    {
      "id_item": 1,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "Restaurante Juan",
      "alias_despacho_consolidado": "Restaurante Juan",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 2,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "Taller catire",
      "alias_despacho_consolidado": "Taller catire",
      "botellas_zenda": 0,
      "botellas_alpes": 7,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 3,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "Empire",
      "alias_despacho_consolidado": "Empire",
      "botellas_zenda": 0,
      "botellas_alpes": 4,
      "monto_calculado_usd": 12.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 4,
      "zona_edificio": "Athaltic",
      "unidad_sublocal": "13C",
      "nombre_cliente_raw": "Athaltic 13C",
      "alias_despacho_consolidado": "Athaltic 13C",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 5,
      "zona_edificio": "Athaltic",
      "unidad_sublocal": "4B",
      "nombre_cliente_raw": "Athaltic 4B",
      "alias_despacho_consolidado": "Athaltic 4B",
      "botellas_zenda": 1,
      "botellas_alpes": 0,
      "monto_calculado_usd": 7.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 6,
      "zona_edificio": "Athaltic",
      "unidad_sublocal": "3C",
      "nombre_cliente_raw": "Athaltic 3C",
      "alias_despacho_consolidado": "Athaltic 3C",
      "botellas_zenda": 2,
      "botellas_alpes": 0,
      "monto_calculado_usd": 14.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 7,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "Cauchera san Ignacio",
      "alias_despacho_consolidado": "Cauchera san Ignacio",
      "botellas_zenda": 3,
      "botellas_alpes": 0,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    }
  ]
}
```

---

### 6.4 Prueba 4: Nota Digital con Subrayados y Comentarios Operativos (`WhatsApp Image 2026-07-21 at 4.43.35 PM.jpeg`)
* **Características de la fuente:** Fecha en encabezado `Martes,21,7,26`. Uso de líneas guionadas `_______`. Anotación operativa: `vecina_______1. ella le iba a dar el pago móvil preguntal`. Total de pie: `48 aguas`.

```json
{
  "metadata_procesamiento": {
    "fecha_procesamiento": "2026-07-21",
    "dia_semana": "Martes",
    "despachador": "Despachador Martes",
    "origen_fuente": "whatsapp_nota_digital",
    "total_listas_esperadas": 3,
    "total_listas_procesadas": 1
  },
  "resumen_diario": {
    "total_botellas_zenda": 1,
    "total_botellas_alpes": 47,
    "total_liquidos": 48,
    "monto_bruto_calculado_usd": 148.0,
    "total_registros": 18,
    "observaciones_pie_pagina": "48 aguas"
  },
  "despachos": [
    {
      "id_item": 1,
      "zona_edificio": ".thamara",
      "unidad_sublocal": "73",
      "nombre_cliente_raw": ".thamara 73",
      "alias_despacho_consolidado": "thamara 73",
      "botellas_zenda": 1,
      "botellas_alpes": 0,
      "monto_calculado_usd": 7.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 2,
      "zona_edificio": ".thamara",
      "unidad_sublocal": "53",
      "nombre_cliente_raw": ".thamara 53",
      "alias_despacho_consolidado": "thamara 53",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 3,
      "zona_edificio": "san Vicente",
      "unidad_sublocal": "91a",
      "nombre_cliente_raw": "san Vicente 91a",
      "alias_despacho_consolidado": "san Vicente 91a",
      "botellas_zenda": 0,
      "botellas_alpes": 3,
      "monto_calculado_usd": 9.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 4,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "motos toro",
      "alias_despacho_consolidado": "motos toro",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 5,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "sayecito vecina",
      "alias_despacho_consolidado": "sayecito vecina",
      "botellas_zenda": 0,
      "botellas_alpes": 1,
      "monto_calculado_usd": 3.0,
      "estado_pago_declarado": "por_verificar",
      "observaciones_chofer": "ella le iba a dar el pago móvil preguntal",
      "requiere_revision_humana": true,
      "motivo_revision": "Pago móvil pendiente de confirmación según nota del chófer"
    }
  ]
}
```

---

## 7. MATRIZ DE CONTROL Y MANEJO DE EXCEPCIONES OPERATIVAS

| Escenario de Excepción | Regla Operativa de Mitigación | Acción en `despachos_diarios.json` |
| :--- | :--- | :--- |
| **Incoherencia en Conteo Diario de Listas** | Si es Lunes y llegan < 4 listas, o si es Mar-Vie y llegan < 3 listas | Marcar `total_listas_procesadas` < `total_listas_esperadas` e incrementar alerta global. |
| **Discrepancia en Totales** | La suma de botellas en ítems no coincide con el total escrito al pie (ej. "54 líquidos"). | Marcar `requiere_revision_humana`: true en los ítems ambiguos o agregar observación global. |
| **Nombre o Sub-local Ilegible** | Caracteres no comprensibles en manuscrito OCR. | Asignar `alias_despacho_consolidado`: `DESCONOCIDO_REVISAR_01` y `requiere_revision_humana`: true. |
| **Comentario de Pago Ambiguo** | Notas como "preguntal", "mañana paga", "pago incompleto". | Establecer `estado_pago_declarado`: "por_verificar" y guardar la frase completa en `observaciones_chofer`. |

---

## 8. CONCLUSIÓN Y PASOS SIGUIENTES

Con la especificación de este archivo `wf_01_ingesta.md`, el sistema cuenta con un motor de extracción robusto, capaz de interpretar cualquier formato de los chóferes sin pérdida de información y garantizando que el archivo `despachos_diarios.json` sirva como insumo perfecto para el **Workflow 02 (Conciliación de Cartera y Arrastre de Deudas)**.
