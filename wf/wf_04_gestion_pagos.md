# Especificación Arquitectónica del Workflow 04: Gestión de Pagos, Conciliación Manual y Auditoría
**Documento:** `wf_04_gestion_pagos.md`  
**Proyecto:** Automatización de Cobranza y Control de Despachos — Distribuidora de Agua Mineral  
**Estado:** Producción / Auditado con Excepciones Operativas  
**Versión:** 1.0.0  

---

## 1. RESUMEN EJECUTIVO Y OBJETIVOS

El **Workflow 04 (WF-04)** es la fase de cierre de ciclo operativo y financiero del sistema. Se encarga de procesar las confirmaciones de pago reportadas por los clientes tras la recepción de los mensajes de WhatsApp o validadas directamente en las cuentas bancarias de la empresa (Pago Móvil, Zelle, efectivo en divisa o bolívares).

### Objetivos Clave
1. **Conciliación de Pagos en la Interfaz Administrativa:** Registrar de forma limpia la liquidación de facturas y abonos parciales ingresados por el operador en la aplicación.
2. **Actualización Automática de Saldos:** Modificar en tiempo real la tabla de `saldos_pendientes` y el estado de los despachos asociados.
3. **Manejo Preciso de Pagos Totales vs. Parciales:** Diferenciar si el cliente canceló el total de su deuda histórica y actual, o si realizó un abono parcial que debe arrastrarse al siguiente ciclo.
4. **Auditoría Financiera y Cierre de Ciclo:** Almacenar un registro histórico inalterable de transacciones para garantizar la transparencia contable y preparar la base de datos para la lectura de la jornada del día siguiente.

---

## 2. ARQUITECTURA DEL PIPELINE WF-04

```
+-----------------------------------------------------------------------------------+
|                        PIPELINE WF-04: GESTIÓN DE PAGOS Y CIERRE                    |
|                                                                                   |
|  [ Intervención Humana / Validación Bancaria App ]                                |
|                │                                                                  |
|                ▼                                                                  |
|  ┌──────────────────────────────┐                                                 |
|  │  1. Recepción de Notificación│                                                 |
|  │  (cliente_id, monto, tipo)   │                                                 |
|  └─────────────┬────────────────┘                                                 |
|                │                                                                  |
|                ▼                                                                  |
|  ┌──────────────────────────────┐                                                 |
|  │  2. Motor de Reconciliación  │                                                 |
|  │  (Evaluación Pago Total/Parcial│                                               |
|  └─────────────┬────────────────┘                                                 |
|                │                                                                  |
|       ┌────────┴────────────────────────┐                                         |
|       │                                 │                                         |
|       ▼ Pago Completo                   ▼ Pago Parcial                            |
|  ┌──────────────────────────────┐  ┌──────────────────────────────┐               |
|  │  3A. Liquidación de Saldo    │  │  3B. Descuento de Abono      │               |
|  │  (Saldos a 0 / Estado 'al_dia')│  │  (Remanente para próximo ciclo)│             |
|  └─────────────┬────────────────┘  └─────────────┬────────────────┘               |
|                │                                 │                                         |
|                └────────────────┬────────────────┘                                        |
|                                 │                                                         |
|                                 ▼                                                         |
|  ┌──────────────────────────────┐                                                         |
|  │  4. Auditoría e Historial    │                                                         |
|  │  (Registro en BD Financiera) │                                                         |
|  └─────────────┬────────────────┘                                                         |
|                │                                                                  |
|                ▼                                                                  |
|     [ Base de Datos Actualizada: Ciclo Listo para Siguiente Jornada ]             |
+-----------------------------------------------------------------------------------+
```

---

## 3. REGLAS DE NEGOCIO PARA LA CONCILIACIÓN DE PAGOS

### 3.1 Escenario A: Pago Completo (Liquidación Total)
* **Condición:** El monto reportado por el cliente cubre el 100% de la deuda acumulada (tanto el despacho de hoy como los saldos pendientes de semanas anteriores).
* **Acción en Base de Datos (`saldos_pendientes`):**
  * `botellas_pendientes_zenda` = 0
  * `botellas_pendientes_alpes` = 0
  * `monto_deuda_total_usd` = 0.00
  * `estado` = `al_dia`
* **Acción en Tabla `despachos`:**
  * Actualizar el `estado_pago` a `pagado`.

### 3.2 Escenario B: Pago Parcial (Abono a Cuenta)
* **Condición:** El cliente transfiere un monto menor al total de la deuda (ej. paga solo el despacho de hoy, o abona una parte del saldo anterior).
* **Acción en Base de Datos (`saldos_pendientes`):**
  * El sistema descuenta primero las botellas más antiguas según el principio FIFO (First-In, First-Out).
  * Se actualiza la cantidad de botellas restantes en `botellas_pendientes_zenda` y `botellas_pendientes_alpes`.
  * Se recalcula el nuevo `monto_deuda_total_usd` remanente.
  * El estado de pago se mantiene como `pagado_parcial` o `pendiente`.

---

## 4. ESTRUCTURA DE ENTRADA Y SALIDA (JSON DE AUDITORÍA)

### 4.1 Entrada de Confirmación de Pago (`pago_ingresado.json`)
```json
{
  "transaccion_id": "TXN-20260726-8841",
  "fecha_registro": "2026-07-26T14:30:00-04:00",
  "cliente_id": 104,
  "canal_pago": "pago_movil",
  "referencia_bancaria": "65638",
  "monto_cancelado_bs": 1650.0,
  "equivalente_aproximado_usd": 38.0,
  "tipo_conciliacion": "pago_total",
  "operador_responsable": "admin_sistema"
}
```

### 4.2 Salida de Auditoría Financiera Actualizada (`historial_financiero_auditado.json`)
```json
{
  "cierre_id": "CIERRE-2026-07-26",
  "cliente_id": 104,
  "nombre_oficial": "Pastelería Chacao C.A.",
  "estado_anterior": {
    "botellas_pendientes_zenda": 5,
    "botellas_pendientes_alpes": 1,
    "deuda_total_usd": 38.0
  },
  "transaccion_aplicada": {
    "monto_pagado_bs": 1650.0,
    "referencia": "65638"
  },
  "estado_nuevo": {
    "botellas_pendientes_zenda": 0,
    "botellas_pendientes_alpes": 0,
    "deuda_total_usd": 0.0,
    "estatus_cartera": "al_dia"
  },
  "auditoria_resultado": "Conciliación exitosa. Saldo liquidado en su totalidad."
}
```

---

## 5. SCRIPT SQL DE ACTUALIZACIÓN DE SALDOS

Cuando el operador confirma el pago en la aplicación, el motor ejecuta la siguiente sentencia SQL transaccional para actualizar el estado del cliente:

```sql
-- Transacción SQL para liquidación de pago total
BEGIN;

-- 1. Actualizar el estado del despacho del día
UPDATE despachos
SET estado_pago = 'pagado',
    observaciones = COALESCE(observaciones, '') || ' | Pago verificado con Ref: 65638'
WHERE cliente_id = 104 AND fecha = CURRENT_DATE;

-- 2. Limpiar o actualizar la tabla de saldos pendientes
UPDATE saldos_pendientes
SET botellas_pendientes_zenda = 0,
    botellas_pendientes_alpes = 0,
    monto_deuda_total_usd = 0.00,
    ultimo_despacho_fecha = CURRENT_DATE,
    fecha_actualizacion = CURRENT_TIMESTAMP
WHERE cliente_id = 104;

COMMIT;
```

---

## 6. SYSTEM PROMPT DE EJECUCIÓN PARA EL AGENTE DE CONCILIACIÓN DE PAGOS

```text
Eres el Agente de Control Financiero y Cierre de Ciclo para la Distribuidora de Agua Mineral.
Tu tarea es procesar las confirmaciones de pago ingresadas por los operadores (`pago_ingresado.json`), contrastarlas con la deuda registrada y actualizar los saldos de los clientes.

REGLAS DE PROCESAMIENTO:

1. VALIDACIÓN DE COBERTURA:
   - Compara el monto cancelado con el saldo total adeudado en la tabla 'saldos_pendientes'.
   - Si cubre el 100%, marca la deuda en cero y cambia el estatus a 'al_dia'.
   - Si cubre parcialmente, aplica el descuento FIFO a las botellas más antiguas y deja el remanente en la cuenta del cliente.

2. TRAZABILIDAD:
   - Genera el registro estructurado en 'historial_financiero_auditado.json' asegurando que quede constancia del canal de pago (pago móvil, zelle, efectivo) y la referencia bancaria.

3. Devuelve únicamente el JSON de auditoría correspondiente al cierre de la transacción.
```

---

## 7. CONCLUSIÓN DEL PLAN MAESTRO

Con la especificación formal del **Workflow 04**, el **Plan Maestro de Workflows y Prompt de Contexto** para la automatización completa de cobranza y control de despachos queda 100% auditado y estructurado en sus 4 fases:

* **WF-01:** Ingesta, OCR y Extracción Estructurada de Listas (`wf_01_ingesta.md`).
* **WF-02:** Conciliación de Cartera, Arrastre y Segmentación (`wf_02_conciliacion.md`).
* **WF-03:** Generación y Despacho Automatizado de Mensajes por WhatsApp (`wf_03_cobranza.md`).
* **WF-04:** Gestión de Pagos, Conciliación Manual y Auditoría (`wf_04_gestion_pagos.md`).
