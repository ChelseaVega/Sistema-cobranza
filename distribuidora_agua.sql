-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-08-2026 a las 19:12:30
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `distribuidora_agua`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas_revision`
--

CREATE TABLE `alertas_revision` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `nombre_raw` varchar(150) NOT NULL,
  `motivo` varchar(100) NOT NULL,
  `datos_item` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`datos_item`)),
  `resuelto` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alertas_revision`
--

INSERT INTO `alertas_revision` (`id`, `fecha`, `nombre_raw`, `motivo`, `datos_item`, `resuelto`, `fecha_creacion`) VALUES
(43, '2026-08-21', 'Cotorro calle', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":1,\"zona_edificio\":\"Cotorro calle\",\"unidad_sublocal\":\"N\\/A\",\"nombre_cliente_raw\":\"Cotorro calle\",\"alias_despacho_consolidado\":\"Cotorro calle\",\"botellas_zenda\":0,\"botellas_alpes\":4,\"monto_calculado_usd\":14,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:37'),
(44, '2026-08-21', 'Arboleda 11B', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":2,\"zona_edificio\":\"Arboleda\",\"unidad_sublocal\":\"11B\",\"nombre_cliente_raw\":\"Arboleda 11B\",\"alias_despacho_consolidado\":\"Arboleda 11B\",\"botellas_zenda\":0,\"botellas_alpes\":2,\"monto_calculado_usd\":7,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(45, '2026-08-21', 'Arboleda 10B', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":3,\"zona_edificio\":\"Arboleda\",\"unidad_sublocal\":\"10B\",\"nombre_cliente_raw\":\"Arboleda 10B\",\"alias_despacho_consolidado\":\"Arboleda 10B\",\"botellas_zenda\":0,\"botellas_alpes\":1,\"monto_calculado_usd\":3.5,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(46, '2026-08-21', 'Arboleda 3A', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":4,\"zona_edificio\":\"Arboleda\",\"unidad_sublocal\":\"3A\",\"nombre_cliente_raw\":\"Arboleda 3A\",\"alias_despacho_consolidado\":\"Arboleda 3A\",\"botellas_zenda\":0,\"botellas_alpes\":1,\"monto_calculado_usd\":3.5,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(47, '2026-08-21', 'Arboleda Conserje', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":5,\"zona_edificio\":\"Arboleda\",\"unidad_sublocal\":\"Conserje\",\"nombre_cliente_raw\":\"Arboleda Conserje\",\"alias_despacho_consolidado\":\"Arboleda Conserje\",\"botellas_zenda\":0,\"botellas_alpes\":1,\"monto_calculado_usd\":3.5,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":\"Conserje\",\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(48, '2026-08-21', 'Quinta Eroz', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":6,\"zona_edificio\":\"Quinta Eroz\",\"unidad_sublocal\":\"N\\/A\",\"nombre_cliente_raw\":\"Quinta Eroz\",\"alias_despacho_consolidado\":\"Quinta Eroz\",\"botellas_zenda\":0,\"botellas_alpes\":1,\"monto_calculado_usd\":3.5,\"estado_pago_declarado\":\"efectivo_bs\",\"monto_pagado_declarado_bs\":1500,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":\"PAGO 1500bs\",\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(49, '2026-08-21', 'Quinta chihuahua', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":7,\"zona_edificio\":\"Quinta chihuahua\",\"unidad_sublocal\":\"N\\/A\",\"nombre_cliente_raw\":\"Quinta chihuahua\",\"alias_despacho_consolidado\":\"Quinta chihuahua\",\"botellas_zenda\":2,\"botellas_alpes\":0,\"monto_calculado_usd\":14,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(50, '2026-08-21', 'Quinta tutoría', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":8,\"zona_edificio\":\"Quinta tutoría\",\"unidad_sublocal\":\"N\\/A\",\"nombre_cliente_raw\":\"Quinta tutoría\",\"alias_despacho_consolidado\":\"Quinta tutoría\",\"botellas_zenda\":0,\"botellas_alpes\":4,\"monto_calculado_usd\":14,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(51, '2026-08-21', 'Quinta trinidad', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":9,\"zona_edificio\":\"Quinta trinidad\",\"unidad_sublocal\":\"N\\/A\",\"nombre_cliente_raw\":\"Quinta trinidad\",\"alias_despacho_consolidado\":\"Quinta trinidad\",\"botellas_zenda\":0,\"botellas_alpes\":4,\"monto_calculado_usd\":14,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":false,\"motivo_revision\":null,\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38'),
(52, '2026-08-21', 'ma Piso 12', 'MATCH_AMBIGUO_O_NO_ENCONTRADO', '{\"id_item\":10,\"zona_edificio\":\"Fatima\",\"unidad_sublocal\":\"Piso 12\",\"nombre_cliente_raw\":\"Fatima Piso 12\",\"alias_despacho_consolidado\":\"ma Piso 12\",\"botellas_zenda\":2,\"botellas_alpes\":0,\"monto_calculado_usd\":14,\"estado_pago_declarado\":\"pendiente\",\"monto_pagado_declarado_bs\":null,\"monto_pagado_declarado_usd\":null,\"referencia_pago\":null,\"observaciones_chofer\":null,\"requiere_revision_humana\":true,\"motivo_revision\":\"El nombre completo del edificio o zona está parcialmente recortado en el borde inferior de la imagen (\'...ma\').\",\"despachador\":\"Gabriel Farias\"}', 0, '2026-08-23 03:09:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `choferes`
--

CREATE TABLE `choferes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre_oficial` varchar(150) NOT NULL,
  `nombre_despacho_alias` varchar(150) NOT NULL,
  `telefono_whatsapp` varchar(20) NOT NULL,
  `categoria` enum('domicilio','local','facturacion_legal') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `despachos`
--

CREATE TABLE `despachos` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `despachador` varchar(100) NOT NULL,
  `chofer_id` int(11) DEFAULT NULL,
  `botellas_zenda` int(11) DEFAULT 0,
  `botellas_alpes` int(11) DEFAULT 0,
  `monto_despacho_usd` decimal(10,2) NOT NULL,
  `estado_pago` enum('pendiente','notificado','pagado_parcial','pagado') DEFAULT 'pendiente',
  `forma_pago_id` int(11) DEFAULT NULL,
  `referencia_pago` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formas_pago`
--

CREATE TABLE `formas_pago` (
  `id` int(11) NOT NULL,
  `nombre_forma` varchar(100) NOT NULL,
  `codigo_identificador` varchar(50) NOT NULL,
  `requiere_referencia` tinyint(1) DEFAULT 0,
  `moneda_defecto` varchar(10) DEFAULT 'USD',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `formas_pago`
--

INSERT INTO `formas_pago` (`id`, `nombre_forma`, `codigo_identificador`, `requiere_referencia`, `moneda_defecto`, `activo`, `fecha_registro`) VALUES
(1, 'Pago Móvil', 'pago_movil', 1, 'BS', 1, '2026-08-03 01:20:27'),
(2, 'Referencia / Transferencia Bancaria', 'transferencia', 1, 'BS', 1, '2026-08-03 01:20:27'),
(3, 'Efectivo Dólares ($)', 'efectivo_usd', 0, 'USD', 1, '2026-08-03 01:20:27'),
(4, 'Efectivo Bolívares Soberanos', 'efeditvo_bs', 0, 'BS', 1, '2026-08-03 01:20:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marcas_agua`
--

CREATE TABLE `marcas_agua` (
  `id` int(11) NOT NULL,
  `nombre_marca` varchar(100) NOT NULL,
  `codigo_identificador` varchar(50) NOT NULL,
  `precio_usd` decimal(10,2) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `marcas_agua`
--

INSERT INTO `marcas_agua` (`id`, `nombre_marca`, `codigo_identificador`, `precio_usd`, `activo`, `fecha_registro`, `fecha_actualizacion`) VALUES
(1, 'La Zenda', 'zenda', 7.00, 1, '2026-08-03 01:20:27', '2026-08-03 01:20:27'),
(2, 'Los Alpes', 'alpes', 3.50, 1, '2026-08-03 01:20:27', '2026-08-03 01:38:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `saldos_pendientes`
--

CREATE TABLE `saldos_pendientes` (
  `cliente_id` int(11) NOT NULL,
  `botellas_pendientes_zenda` int(11) DEFAULT 0,
  `botellas_pendientes_alpes` int(11) DEFAULT 0,
  `monto_deuda_total_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ultimo_despacho_fecha` date DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password`, `nombre`, `fecha_registro`) VALUES
(1, 'admin', '$2y$10$IaGnnEmPDqYfudh7Bt0X6eXtfviNZmAXXU2RC0I2gCUe1xqQCXTg6', 'Administrador General', '2026-08-03 01:20:27');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas_revision`
--
ALTER TABLE `alertas_revision`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `choferes`
--
ALTER TABLE `choferes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_despacho_alias` (`nombre_despacho_alias`);

--
-- Indices de la tabla `despachos`
--
ALTER TABLE `despachos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_despachos_cliente` (`cliente_id`),
  ADD KEY `fk_despachos_forma_pago` (`forma_pago_id`),
  ADD KEY `fk_despachos_chofer` (`chofer_id`);

--
-- Indices de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_forma` (`nombre_forma`),
  ADD UNIQUE KEY `codigo_identificador` (`codigo_identificador`);

--
-- Indices de la tabla `marcas_agua`
--
ALTER TABLE `marcas_agua`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_marca` (`nombre_marca`),
  ADD UNIQUE KEY `codigo_identificador` (`codigo_identificador`);

--
-- Indices de la tabla `saldos_pendientes`
--
ALTER TABLE `saldos_pendientes`
  ADD PRIMARY KEY (`cliente_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas_revision`
--
ALTER TABLE `alertas_revision`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `choferes`
--
ALTER TABLE `choferes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `despachos`
--
ALTER TABLE `despachos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `marcas_agua`
--
ALTER TABLE `marcas_agua`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `despachos`
--
ALTER TABLE `despachos`
  ADD CONSTRAINT `fk_despachos_chofer` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_despachos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_despachos_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `saldos_pendientes`
--
ALTER TABLE `saldos_pendientes`
  ADD CONSTRAINT `fk_saldos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
