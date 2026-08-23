# Especificación Arquitectónica del Workflow 02: Conciliación de Cartera, Arrastre de Deudas y Segmentación
**Documento:** `wf_02_conciliacion.md`  
**Proyecto:** Automatización de Cobranza y Control de Despachos — Distribuidora de Agua Mineral  
**Estado:** Producción / Auditado con Excepciones Operativas  
**Versión:** 1.0.0  

---

## 1. RESUMEN EJECUTIVO Y LÓGICA DEL PROCESO

El **Workflow 02 (WF-02)** es el motor central de conciliación e inteligencia de datos del sistema. Toma como insumo principal el archivo estructurado `despachos_diarios.json` (generado en WF-01) y lo cruza contra la **Base de Datos Operativa** (tablas `clientes`, `despachos` y `saldos_pendientes`).

### Objetivos Clave
1. **Normalización y Cruzamiento de Nombres (Fuzzy Matching):** Resolver las discrepancias entre cómo escribe el chofer a mano o por WhatsApp (`alias_despacho_consolidado`) y el registro formal en BD (`nombre_oficial`, `nombre_despacho_alias`).
2. **Control Estricto de Arrastre de Deudas (Clientes Inactivos):** Identificar a los clientes que **NO** recibieron despacho en el día actual pero mantienen saldos adeudados de semanas anteriores, garantizando su inclusión continua en el ciclo de seguimiento.
3. **Aplicación de Políticas de Segmentación (`facturacion_legal`):** Desviar automáticamente a los clientes especiales/legales hacia el registro de inventario, impidiendo que reciban notificaciones automáticas diarias de WhatsApp.
4. **Separación de Salidas y Enrutamiento de Excepciones:** Generar `cola_cobranza.json` para clientes listos para notificación y `alertas_revision.json` para discrepancias que requieran resolución por parte del operador humano.

---

## 2. ESQUEMA DDL DE BASE DE DATOS (ESPECIFICACIÓN ARQUITECTÓNICA)

Aunque la base de datos se implementará físicamente en la fase de despliegue, la especificación técnica de WF-02 define las siguientes tablas y relaciones SQL:

```sql
-- TABLA DE CLIENTES
CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nombre_oficial VARCHAR(150) NOT NULL,
    nombre_despacho_alias VARCHAR(150) NOT NULL, -- Alias habitual utilizado por choferes
    telefono_whatsapp VARCHAR(20) NOT NULL,
    categoria VARCHAR(30) NOT NULL CHECK (categoria IN ('domicilio', 'local', 'facturacion_legal')),
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLA DE HISTORIAL DE DESPACHOS
CREATE TABLE despachos (
    id SERIAL PRIMARY KEY,
    fecha DATE NOT NULL,
    cliente_id INT NOT NULL REFERENCES clientes(id),
    despachador VARCHAR(100) NOT NULL,
    botellas_zenda INT DEFAULT 0,
    botellas_alpes INT DEFAULT 0,
    monto_despacho_usd NUMERIC(10, 2) NOT NULL,
    estado_pago VARCHAR(20) DEFAULT 'pendiente' CHECK (estado_pago IN ('pendiente', 'pagado_parcial', 'pagado')),
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLA DE SALDOS PENDIENTES (CARTERA Y ARRASTRE)
CREATE TABLE saldos_pendientes (
    cliente_id INT PRIMARY KEY REFERENCES clientes(id),
    botellas_pendientes_zenda INT DEFAULT 0,
    botellas_pendientes_alpes INT DEFAULT 0,
    monto_deuda_total_usd NUMERIC(10, 2) NOT NULL,
    ultimo_despacho_fecha DATE,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLA DE AUDITORÍA Y ALERTAS
CREATE TABLE alertas_revision (
    id SERIAL PRIMARY KEY,
    fecha DATE NOT NULL,
    nombre_raw VARCHAR(150) NOT NULL,
    motivo VARCHAR(100) NOT NULL,
    datos_item JSONB NOT NULL,
    resuelto BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 3. ARQUITECTURA DE PIPELINE WF-02

```
+-----------------------------------------------------------------------------------+
|                        PIPELINE WF-02: CONCILIACIÓN Y ARRASTRE                   |
|                                                                                   |
|    [ despachos_diarios.json ]               [ BD: clientes, saldos_pendientes ]   |
|                 │                                           │                     |
|                 └───────────────────┬───────────────────────┘                     |
|                                     ▼                                             |
|                     ┌──────────────────────────────┐                              |
|                     │   1. Entity Matching (Fuzzy) │                              |
|                     │   (Tolerancia >= 85%)        │                              |
|                     └──────────────┬───────────────┘                              |
|                                    │                                              |
|                 ┌──────────────────┴──────────────────┐                           |
|                 │ Match exitoso                        │ Coincidencia dudos / <85%|
|                 ▼                                     ▼                           |
|  ┌──────────────────────────────┐          ┌──────────────────────────────┐       |
|  │ 2. Filtrado Categorías       │          │  [ alertas_revision.json ]   │       |
|  │ (facturacion_legal vs resto) │          └──────────────────────────────┘       |
|  └──────────────┬───────────────┘                                                 |
|                 │                                                                 |
|                 ├───> Si 'facturacion_legal': Registra en BD Despachos              |
|                 │     y excluye de WhatsApp.                                      |
|                 │                                                                 |
|                 ▼                                                                 |
|  ┌──────────────────────────────┐                                                 |
|  │ 3. Cálculo de Arrastre       │                                                 |
|  │ (Clientes hoy + Inactivos)   │                                                 |
|  └──────────────┬───────────────┘                                                 |
|                 │                                                                 |
|                 ▼                                                                 |
|     [ cola_cobranza.json ]                                                        |
+-----------------------------------------------------------------------------------+
```

---

## 4. ALGORITMO Y REGLAS DE NEGOCIO DETALLADAS

### 4.1 Cruzamiento de Entidades (Fuzzy Matching Algorithm)
Para empatar el campo `alias_despacho_consolidado` del JSON contra `nombre_despacho_alias` de la tabla `clientes`:
1. **Normalización previa:** Convertir ambos textos a mayúsculas, eliminar tildes, signos de puntuación y espacios dobles.
2. **Cálculo de Similitud (Token Sort Ratio / Levenshtein):**
   * **Similitud >= 85%:** Match confiable. Se asigna automáticamente el `cliente_id`.
   * **Similitud < 85%:** Se marca como registro no coincidente y se envía a `alertas_revision.json` suspendiendo el despacho para evitar cobros a clientes equivocados.

### 4.2 Segmentación por Categoría (`facturacion_legal`)
* Si `cliente.categoria == 'facturacion_legal'`:
  1. Insertar el registro de consumo diario en la tabla SQL `despachos`.
  2. Actualizar la tabla `saldos_pendientes`.
  3. **EXCLUIR** al cliente de `cola_cobranza.json`. No se le envía ningún mensaje de WhatsApp diario (su consolidación de facturación se realiza de manera administrativa a fin de mes).

### 4.3 Regla de Arrastre de Deudas (Clientes Inactivos)
Para garantizar que ningún cliente con saldo pendiente deje de ser notificado por el hecho de no haber recibido despacho en el día actual:
1. El agente consulta en `saldos_pendientes` todos los clientes donde `(botellas_pendientes_zenda > 0 OR botellas_pendientes_alpes > 0)`.
2. **Subconjunto A (Con Despacho Hoy):**
   * `botellas_hoy_zenda` = Valor en JSON.
   * `botellas_hoy_alpes` = Valor en JSON.
   * `botellas_acumuladas_zenda` = `saldos_pendientes.botellas_pendientes_zenda`.
   * `botellas_acumuladas_alpes` = `saldos_pendientes.botellas_pendientes_alpes`.
3. **Subconjunto B (Inactivos - Sin Despacho Hoy):**
   * `botellas_hoy_zenda` = 0.
   * `botellas_hoy_alpes` = 0.
   * `botellas_acumuladas_zenda` = `saldos_pendientes.botellas_pendientes_zenda`.
   * `botellas_acumuladas_alpes` = `saldos_pendientes.botellas_pendientes_alpes`.
4. Ambos subconjuntos se unen para conformar la **`cola_cobranza.json`**.

### 4.4 Cálculo Financiero de Respaldo en BD
Aunque el texto de WhatsApp **NO** muestra montos en dólares (cumpliendo con la directiva del negocio), el sistema calcula internamente la deuda financiera para registros contables:
$$	ext{Monto Deuda USD} = (	ext{Total Botellas Zenda} 	imes 7.00) + (	ext{Total Botellas Alpes} 	imes 3.00)$$

---

## 5. ESQUEMA JSON DE LAS SALIDAS DEL WORKFLOW

### 5.1 Esquema de `cola_cobranza.json`
```json
{
  "fecha_generacion": "2026-07-26",
  "total_clientes_cola": 2,
  "cola_notificaciones": [
    {
      "cliente_id": 104,
      "nombre_oficial": "Pastelería Chacao C.A.",
      "telefono_whatsapp": "+584121234567",
      "categoria": "local",
      "despacho_hoy": {
        "recibio_hoy": true,
        "botellas_zenda_hoy": 3,
        "botellas_alpes_hoy": 0,
        "total_hoy": 3
      },
      "saldos_anteriores": {
        "botellas_zenda_pendientes": 2,
        "botellas_alpes_pendientes": 1,
        "total_pendientes": 3
      },
      "totales_consolidados": {
        "total_botellas_zenda": 5,
        "total_botellas_alpes": 1,
        "total_botellas_global": 6,
        "monto_deuda_total_usd": 38.0
      },
      "estado_pago_declarado_hoy": "pendiente"
    },
    {
      "cliente_id": 88,
      "nombre_oficial": "Residencias Tucurabua Apt 3-05",
      "telefono_whatsapp": "+584149876543",
      "categoria": "domicilio",
      "despacho_hoy": {
        "recibio_hoy": false,
        "botellas_zenda_hoy": 0,
        "botellas_alpes_hoy": 0,
        "total_hoy": 0
      },
      "saldos_anteriores": {
        "botellas_zenda_pendientes": 0,
        "botellas_alpes_pendientes": 4,
        "total_pendientes": 4
      },
      "totales_consolidados": {
        "total_botellas_zenda": 0,
        "total_botellas_alpes": 4,
        "total_botellas_global": 4,
        "monto_deuda_total_usd": 12.0
      },
      "estado_pago_declarado_hoy": "inactivo_con_deuda"
    }
  ]
}
```

### 5.2 Esquema de `alertas_revision.json`
```json
{
  "fecha_generacion": "2026-07-26",
  "total_alertas": 1,
  "alertas": [
    {
      "id_item_origen": 5,
      "alias_despacho_consolidado": "sayecito vecina",
      "motivo_alerta": "MATCH_AMBIGUO_O_NO_ENCONTRADO",
      "porcentaje_coincidencia_maximo": 62,
      "candidato_sugerido": "Sayeco Repuestos (ID 45)",
      "datos_despacho_raw": {
        "botellas_zenda": 0,
        "botellas_alpes": 1,
        "observaciones": "ella le iba a dar el pago móvil preguntal"
      },
      "accion_requerida": "El operador debe asociar manualmente este despacho al cliente correcto en la app."
    }
  ]
}
```

---

## 6. PROMPT DE CONTEXTO Y LÓGICA DE EJECUCIÓN PARA EL AGENTE DE CONCILIACIÓN

```text
Eres el Agente Experto de Conciliación de Cartera y Control de Deudas para la distribuidora de agua mineral.
Tu objetivo es procesar el archivo `despachos_diarios.json` y cruzarlo con el catálogo de Clientes y Saldos Pendientes para generar la `cola_cobranza.json` y `alertas_revision.json`.

REGLAS DE PROCESAMIENTO PASO A PASO:

1. FUZZY MATCHING & IDENTIFICACIÓN:
   - Para cada ítem en `despachos_diarios.json`, busca la coincidencia de `alias_despacho_consolidado` contra la base de clientes.
   - Si la coincidencia es < 85%, NO asumas el cliente. Asigna el registro a `alertas_revision.json` y detén la cobranza para dicho ítem.

2. SEGMENTACIÓN FACTURACIÓN LEGAL:
   - Si el cliente emparejado pertenece a `categoria: "facturacion_legal"`, registra su consumo en el historial de despachos de la BD, pero NUNCA lo agregues a `cola_cobranza.json`.

3. CÁLCULO DE ARRASTRE Y TOTALES:
   - Para clientes con despacho hoy:
     * total_zenda = botellas_zenda_hoy + botellas_zenda_pendientes
     * total_alpes = botellas_alpes_hoy + botellas_alpes_pendientes
   - Para clientes SIN despacho hoy pero con saldo adeudado en `saldos_pendientes`:
     * Inclúyelos en `cola_cobranza.json` con sus botellas adeudadas acumuladas de semanas anteriores.

4. CÁLCULO FINANCIERO DE RESPALDO:
   - Calcula monto_deuda_total_usd = (total_zenda * 7.00) + (total_alpes * 3.00).

Devuelve únicamente los objetos JSON validados para `cola_cobranza.json` y `alertas_revision.json`.
```

---

## 7. CONCLUSIÓN

El **Workflow 02** blinda la operación financiera del negocio al prevenir errores de cobro por discrepancias de nombres, asegurar que los clientes con facturación legal no sean molestados por WhatsApp, y garantizar el cobro continuo de deudas pendientes aun cuando el cliente no haya comprado agua en el día actual.
