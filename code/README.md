# Distribuidora de Agua Mineral — Automatización de Cobranza y Despachos

Este sistema web completo permite controlar los despachos diarios, conciliar la cartera de deudas de clientes (Fuzzy Matching para emparejamiento), gestionar la cobranza en WhatsApp (con la invariante de $0 importes financieros) y procesar los cobros bajo criterio FIFO.

---

## Stack Tecnológico
* **Backend**: PHP 8.x Vanilla (endpoints estructurados en formato JSON).
* **Frontend**: HTML5, CSS3 Premium (paleta `#2077F9`, `#00001C`, `#F2F2F2`) y JavaScript ES6 Puro (Fetch API).
* **Base de Datos**: MySQL / MariaDB (InnoDB, Charset `utf8mb4`).

---

## Requisitos e Instalación

### 1. Servidor Local (XAMPP)
Asegúrese de tener XAMPP instalado con soporte para **PHP 8.x** y **MySQL**.

### 2. Crear Enlace Simbólico (Opción Recomendada)
Para ejecutar la aplicación localmente en el puerto `8000` u otro de Apache, cree un enlace simbólico desde la consola de Windows (CMD ejecutada como **Administrador**):
```cmd
mklink /D "C:\xampp\htdocs\distribuidora_agua" "C:\Users\Chelsea Vega\OneDrive\Desktop\project antigravity\code"
```

### 3. Base de Datos
1. Acceda a **phpMyAdmin** (`http://localhost:8000/phpmyadmin` o el puerto correspondiente).
2. Cree una base de datos llamada `distribuidora_agua`.
3. Importe el archivo SQL de estructura y datos semilla localizado en:
   `code/database/schema.sql`.

---

## Credenciales de Acceso
El sistema cuenta con autenticación real contra la base de datos:
* **Usuario**: `admin`
* **Contraseña**: `admin`

---

## Flujo de Prueba de Extremo a Extremo (E2E)

1. **Inicio de Sesión**: Entre a `http://localhost:8000/distribuidora_agua/` e ingrese con las credenciales `admin` / `admin`.
2. **Carga de Ingesta**:
   - Vaya a la sección **Ingesta OCR** en el sidebar.
   - Arrastre o seleccione el archivo de pruebas `despachos_diarios_ejemplo.json` (incluido en la raíz de la carpeta `code/`).
   - Verifique en pantalla la previsualización con el desglose de botellas y los montos en dólares calculados según el catálogo dinámico de precios.
   - Presione el botón **Procesar e Importar Ingesta**.
3. **Conciliación**:
   - Regrese al **Panel de Control** (Dashboard).
   - Presione el botón **Ejecutar Conciliación del Día**.
   - El sistema ejecutará el Fuzzy Matching. Los despachos con clientes reconocidos con una similitud $\ge 85\%$ se guardarán en el historial; aquellos con nombres desconocidos o ambiguos (como `"sayecito vecina"`) generarán una alerta en la barra de KPIs.
4. **Resolución de Alertas**:
   - Vaya a **Alertas de Nombres**.
   - Verifique la alerta generada para `"sayecito vecina"`. Verá que el sistema sugiere automáticamente el cliente `"Sayeco Repuestos"` con el porcentaje de coincidencia.
   - Presione **Resolver**, busque y seleccione el cliente oficial en el autocompletador y presione **Vincular y Confirmar**. El despacho se registrará correctamente y el saldo del cliente se actualizará.
5. **WhatsApp (Copiado de Mensajes)**:
   - En el Dashboard, en la sección **Cola de Cobranza del Día**, aparecerán los clientes a notificar.
   - Verifique que la columna de botellas y el mensaje a copiar **NO** contengan montos monetarios en dólares ni bolívares.
   - Haga clic en **Copiar Mensaje**. Se copiará el texto al portapapeles y el estatus en base de datos cambiará a `notificado`.
6. **Cobro FIFO**:
   - Vaya a la vista de **Registro de Pagos**.
   - En el panel derecho podrá visualizar la cartera total y saldos de clientes.
   - Busque a `"Pastelería Chacao C.A."` en el formulario izquierdo, seleccione el método **Pago Móvil** (el cual habilitará el input de referencia bancaria), ingrese la referencia `65638` y abone `$38.00` USD (pago total).
   - Registre el pago. Podrá ver cómo su saldo se actualiza a **AL DÍA** y las botellas adeudadas quedan en 0 en tiempo real.
