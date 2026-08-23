
# Investigación Integral: Fundamentos, Estructura, Arquitectura, Estándares Normativos, Teoría Distribuida y Ciclo de Vida de Sistemas de Software

## 1. Definición Ontológica y Delimitación Conceptual

Un **sistema a nivel de software** es un conjunto organizado, interactivo e interdependiente de componentes lógicos (módulos, servicios, bases de datos, configuraciones e interfaces) estructurados bajo una arquitectura definida para procesar datos de entrada (*inputs*), ejecutar transformaciones algorítmicas y producir resultados de salida (*outputs*) que resuelven problemas complejos de negocio o de cómputo.

Según la norma **ISO/IEC/IEEE 42010**, un sistema no debe entenderse solo como código ejecutable, sino como una **abstracción arquitectónica** orientada a satisfacer necesidades de partes interesadas (*stakeholders*) bajo restricciones operativas, técnicas y de entorno.

### 1.1. Diferenciación Ontológica

* **Programa:** Secuencia finita de instrucciones legibles por máquina diseñadas para ejecutar una tarea computacional específica (ej. un algoritmo de ordenamiento).
* **Aplicación:** Software directamente orientado al usuario final para realizar una función delimitada (ej. un editor de texto o una calculadora).
* **Sistema de Software:** Infraestructura lógica integral que engloba múltiples programas, servicios, almacenes de datos y capas de abstracción operando de forma coordinada (ej. un *Core* Bancario o un ERP).
* **Sistema de Sistemas (SoS):** Integración de múltiples sistemas de software constitutivos que son operativamente autónomos y gestorialmente independientes, pero que colaboran mediante red e interfaces para ofrecer capacidades emergentes de alto nivel que ningún sistema podría lograr por separado.

### 1.2. Taxonomía de los Sistemas de Software

* **Por Despliegue:** Monolíticos vs. Distribuidos / Microservicios.
* **Por Procesamiento:** En Lote (*Batch*) vs. Tiempo Real (*Real-time / Streaming*).
* **Por Lógica Interna:** Deterministas (reglas estáticas) vs. Estocásticos / Basados en IA (modelos probabilísticos e inferencia).

---

## 2. Propósito Fundamental y Dominios de Aplicación

Los sistemas de software se crean para trascender las limitaciones biológicas, temporales y cognitivas del ser humano en el procesamiento de información:

1. **Automatización Algorítmica:** Eliminación de tareas manuales repetitivas con precisión determinista y repetibilidad ininterrumpida.
2. **Procesamiento y Escalabilidad a Gran Escala:** Capacidad para estructurar, validar y analizar grandes volúmenes de datos (*Petabytes*) en milisegundos.
3. **Mitigación de Riesgos y Simulación:** Optimización de recursos, control de entornos físicos y predicción de fallas en dominios de alta criticidad.

### Campos de Aplicación

* **Sistemas Críticos y Tiempo Real:** Control del tráfico aéreo, soporte vital médico, centrales nucleares.
* **Sistemas Empresariales y Financieros:** ERP, CRM, procesamiento de pagos con garantías transaccionales.
* **Infraestructura y Control:** Sistemas operativos, hipervisores, orquestadores de contenedores y nodos IoT/Sistemas embebidos.

---

## 3. Metamodelo Arquitectónico y Componentes Estructurales

### 3.1. Metamodelo de Arquitectura (ISO/IEC/IEEE 42010)

| Entidad del Metamodelo | Definición y Función en el Sistema |
| --- | --- |
| **Sistema de Interés (*System-of-Interest*)** | El sistema de software específico que se concibe, desarrolla o evalúa. |
| **Interesado (*Stakeholder*)** | Entidad (usuario, arquitecto, negocio) con intereses o preocupaciones (*concerns*) en el sistema. |
| **Descripción de Arquitectura (AD)** | Artefacto documental que expresa explícitamente la arquitectura del sistema. |
| **Puntos de Vista (*Viewpoints*) y Vistas (*Views*)** | Marcos de trabajo y representaciones parciales que responden a preocupaciones específicas (estructural, dinámica, despliegue). |
| **Reglas de Correspondencia** | Restricciones semánticas que garantizan la consistencia sin contradicciones entre diferentes vistas y modelos. |

### 3.2. Arquitectura de Referencia Multicapa y Distribución de Componentes

```text
+-----------------------------------------------------------------------+
|            Capa de Perímetro y Seguridad (WAF / Anti-DDoS / CDN)       |
+-----------------------------------------------------------------------+
                                   |
                                   v
+-----------------------------------------------------------------------+
|               Capa de Presentación / Cliente (Frontend)               |
+-----------------------------------------------------------------------+
                                   | (REST / gRPC / WebSockets)
                                   v
+-----------------------------------------------------------------------+
|               Capa de Interoperabilidad / API Gateway                 |
+-----------------------------------------------------------------------+
                                   |
                                   v
+-----------------------------------------------------------------------+
|            Capa de Lógica de Negocio / Backend (Servicios)            |
+-----------------------------------------------------------------------+
              |                                             |
              v                                             v
+-------------------------------+             +--------------------------+
| Capa de Caché en Memoria      |             | Brokers de Mensajería    |
| (Redis / Memcached)           |             | (Kafka / RabbitMQ)       |
+-------------------------------+             +--------------------------+
              |                                             |
              +--------------------+------------------------+
                                   |
                                   v
+-----------------------------------------------------------------------+
|              Capa de Persistencia (RDBMS / NoSQL / Blobs)               |
+-----------------------------------------------------------------------+

```

### 3.3. Desglose Módulo por Módulo

* **Perímetro y Seguridad:** Filtros WAF (inspección de SQLi/XSS) y mitigación DDoS.
* **Presentación / Frontend:** GUIs web, aplicaciones móviles nativas y CLIs para la interacción con el usuario.
* **API Gateway:** Enrutamiento, terminación TLS, autenticación, *rate limiting* y agregación de peticiones.
* **Lógica de Negocio (Backend):** Implementación de las reglas del dominio, motores de cálculo y flujos de trabajo.
* **Caché (*In-Memory*):** Almacenamiento RAM de ultra baja latencia (ej. Redis) para aliviar la persistencia.
* **Persistencia:** Bases de datos relacionales con ACID (PostgreSQL, Oracle), NoSQL para datos semiestructurados (MongoDB, Cassandra) y almacenamiento de objetos para binarios/blobs (AWS S3).
* **Middleware y Eventos:** Transmisión asíncrona mediante comunicación orientada a eventos (EDA) o colas Pub/Sub (Apache Kafka, RabbitMQ).
* **Componentes Cruzados (*Cross-Cutting Concerns*):**
* *IAM:* Gestión de Identidad y Accesos (OAuth2, JWT, RBAC).
* *Observabilidad:* Métricas (Prometheus), Logs estructurados y Trazado Distribuido (OpenTelemetry, Jaeger).
* *Resiliencia:* Patrones *Circuit Breaker* (interrupción ante fallas continuas), *Bulkhead* (aislamiento de recursos) y *Graceful Degradation* (degradación graciosa del servicio).



---

## 4. Funciones, Operaciones Transaccionales y Teoría Distribuida

### 4.1. Ciclo de Procesamiento y Adecuación Funcional (ISO/IEC 25010)

El ciclo clásico **Input $\rightarrow$ Process $\rightarrow$ Store $\rightarrow$ Output** se rige por la **Adecuación Funcional**:

* **Completitud Funcional:** Grado en que las funciones cubren la totalidad de las tareas especificadas.
* **Corrección Funcional:** Precisión y rigor cuantitativo/algorítmico en las salidas producidas.
* **Pertinencia Funcional:** Capacidad para resolver el objetivo sin pasos redundantes o sobrecostos operacionales.

### 4.2. Teoría de Sistemas Distribuidos

1. **Garantías Transaccionales (ACID vs. BASE):**
* *ACID (Atomicidad, Consistencia, Aislamiento, Durabilidad):* Típico de sistemas bancarios donde la consistencia matemática inmediata es obligatoria.
* *BASE (Basically Available, Soft-state, Eventual consistency):* Típico de sistemas distribuidos masivos donde se prioriza la disponibilidad sobre la consistencia inmediata.


2. **Teorema CAP (Teorema de Brewer):** En presencia de una partición de red ($P$), todo sistema distribuido debe optar entre **Consistencia ($C$)** o **Disponibilidad ($A$)**.
3. **Teorema PACELC:** Extensión del teorema CAP:

$$\text{Si hay Partición } (P) \Rightarrow \text{Elegir entre Availability } (A) \text{ o Consistency } (C);$$


$$\text{Else } (E) \Rightarrow \text{Elegir entre Latency } (L) \text{ o Consistency } (C).$$



---

## 5. Parámetros Técnicos, Métricas y Modelo de Calidad (ISO/IEC 25010)

El modelo de calidad de software desglosa los parámetros operativos en características cuantitativamente medibles:

| Característica ISO 25010 | Subcaracterísticas | Parámetro Técnico y Métricas Formales |
| --- | --- | --- |
| **Eficiencia de Desempeño** | Comportamiento temporal, Recursos, Capacidad | Latencia ($L$), Uso de CPU/RAM, Rendimiento / Caudal de Procesamiento (*Throughput*):<br>

<br>$$T = \frac{N}{\Delta t}$$

<br> (donde $N$ es el número de solicitudes en el intervalo $\Delta t$). |
| **Fiabilidad / Disponibilidad** | Tolerancia a fallos, Recuperabilidad, Madurez | Medido en "nueves" de acuerdo de servicio (SLA):<br>

<br>$$\text{Disponibilidad (\%)} = \left( \frac{\text{MTBF}}{\text{MTBF} + \text{MTTR}} \right) \times 100$$

<br>

<br>*(MTBF: Tiempo medio entre fallas; MTTR: Tiempo medio de reparación)*. |
| **Mantenibilidad** | Modularidad, Reusabilidad, Modificabilidad, Testabilidad | Complejidad Ciclomática de McCabe ($V(G) = E - N + 2P$), Índice de Mantenibilidad y Porcentaje de Cobertura de Código. |
| **Seguridad** | Confidencialidad, Integridad, No repudio, Trazabilidad | Longitud de claves criptográficas, tasa de vulnerabilidades no mitigadas y cobertura de auditoría de *logs*. |
| **Compatibilidad y Portabilidad** | Interoperabilidad, Coexistencia, Adaptabilidad | Tasa de éxito en intercambio de datos, tiempo de despliegue en entornos heterogéneos. |
| **Capacidad de Interacción** | Operabilidad, Accesibilidad, Inclusividad | Tasa de error del usuario, cumplimiento de normas WCAG, tiempo de aprendizaje. |
| **Seguridad Operacional (*Safety*)** | Mitigación de riesgos, *Fail-safe* | Probabilidad de transición a estados catastróficos no deseados ante fallas físicas. |

---

## 6. Proceso Metodológico de Creación: Ciclo de Vida e Ingeniería de Software (ISO/IEC/IEEE 12207)

El estándar **ISO/IEC/IEEE 12207** establece la estructura de procesos del ciclo de vida dividida en procesos principales y de soporte:

```text
                  [ Procesos Principales (Adquisición / Suministro) ]
                                          |
                                          v
[ 1. Análisis de Requisitos ] ---> [ 2. Diseño Arquitectónico ] ---> [ 3. Codificación / Construcción ]
          (ISO 29148)                     (ISO 42010)                      (SOLID / Patrones)
                                                                                  |
                                                                                  v
[ 6. Operación y Mantenimiento ] <-- [ 5. Despliegue y CI/CD ] <--- [ 4. Integración y Pruebas ]
          (ISO 12207)                      (IaC / Docker / K8s)                 (ISO 29119)
                                          ^
                                          |
                  [ Procesos de Soporte (SCM / SQA / V&V / Auditorías) ]

```

### 6.1. Fases Técnicas de Ejecución y Prácticas de Ingeniería

1. **Análisis de Requisitos (ISO/IEC/IEEE 29148):** Levantamiento de Requisitos Funcionales (RF) y No Funcionales (RNF).
2. **Diseño y Arquitectura:** Selección de patrones arquitectónicos, modelado de datos y definición de contratos de API.
3. **Construcción e Implementación:** Aplicación de buenas prácticas y principios **SOLID**:
* *Single Responsibility:* Una sola razón para cambiar.
* *Open/Closed:* Abierto a extensión, cerrado a modificación.
* *Liskov Substitution:* Subtipos sustituibles por sus tipos base.
* *Interface Segregation:* Interfaces específicas mejor que universales.
* *Dependency Inversion:* Depender de abstracciones, no de concreciones.


4. **Pruebas y QA (ISO/IEC/IEEE 29119):** Ejecución de Pruebas Unitarias, de Integración, End-to-End (E2E) y Pruebas de Carga/Estrés.
5. **Despliegue, DevOps e Infraestructura como Código (IaC):**
* *CI/CD:* Automatización de compilación, pruebas y entrega.
* *IaC y Contenedores:* Aprovisionamiento declarativo de infraestructura (Terraform) y empaquetado portátil (Docker) orquestado a escala (Kubernetes).
* *Estrategias de Despliegue:* *Blue/Green* o publicaciones *Canary* para mitigar riesgos en producción.


6. **Operación y Mantenimiento (ISO 12207):** Gestión de tipos de mantenimiento: Correctivo (bugs), Adaptativo (entorno), Perfectivo/Evolutivo (nuevas características) y Preventivo.

### 6.2. Mapeo Normativo de Entregables

| Fase del Ciclo de Vida | Entregables y Artefactos Técnicos | Estándar Normativo Aplicable |
| --- | --- | --- |
| **Elicitación y Análisis** | Especificación de Requisitos de Software (SRS), Historias de Usuario | ISO/IEC/IEEE 29148 / SWEBOK |
| **Diseño Arquitectónico** | Descripción de Arquitectura (AD), Vistas y Matrices de Correspondencia | ISO/IEC/IEEE 42010 |
| **Construcción y Módulos** | Repositorio de Código, Pruebas Unitarias, Pipelines CI/CD, Documentación API | ISO/IEC/IEEE 12207 |
| **Verificación y Validación** | Plan de Pruebas, Casos de Prueba, Matriz de Trazabilidad, Informes de Defectos | ISO/IEC/IEEE 29119 / SQuaRE |
| **Operación y Mantenimiento** | Scripts de Despliegue (IaC), Manuales de Operación, Registros de Auditoría, Parches | ISO/IEC/IEEE 12207 / ISO 25010 |

---

## 7. Matriz Resumen Integral del Sistema de Software

| Dimensión | Detalle Operativo y Normativo |
| --- | --- |
| **Definición** | Entidad lógica interconectada regulada por la ISO 42010 para procesar datos y transformar estados. |
| **Escala** | Desde programas individuales hasta ecosistemas distribuidos de Sistemas de Sistemas (SoS). |
| **Componentes Principales** | Frontend, API Gateway, Backend, Caché (Redis), Persistencia (RDBMS/NoSQL), Brokers (Kafka) y WAF. |
| **Marco de Calidad** | Evaluación cuantitativa bajo ISO 25010 (Adecuación Funcional, Rendimiento, Fiabilidad, *Safety*, etc.). |
| **Teoría Distribuida** | Garantías ACID vs. BASE, Teorema CAP y PACELC. |
| **Métricas Clave** | Throughput ($T$), Disponibilidad (%), Latencia ($L$), Complejidad Ciclomática de McCabe ($V(G)$). |
| **Gobernanza del Ciclo de Vida** | Procesos principales y de soporte estipulados en la norma ISO/IEC/IEEE 12207 integrados con prácticas DevSecOps. |