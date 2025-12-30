-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: bkzonlznatzzfkelstum-mysql.services.clever-cloud.com:3306
-- Tiempo de generación: 30-12-2025 a las 01:32:49
-- Versión del servidor: 8.0.22-13
-- Versión de PHP: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bkzonlznatzzfkelstum`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos_compras`
--

CREATE TABLE `abonos_compras` (
  `id_abono` int NOT NULL,
  `id_compra` int NOT NULL,
  `id_usuario` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_abono` date NOT NULL,
  `forma_pago` enum('Efectivo','Tarjeta Débito','Tarjeta Crédito','Transferencia','Cheque') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `abonos_compras`
--

INSERT INTO `abonos_compras` (`id_abono`, `id_compra`, `id_usuario`, `monto`, `fecha_abono`, `forma_pago`, `referencia`, `observaciones`, `created_at`) VALUES
(1, 2, 1, 2500.00, '2025-12-09', 'Efectivo', '', '', '2025-12-10 05:12:26'),
(2, 3, 1, 750.00, '2025-12-09', 'Efectivo', '', '', '2025-12-10 05:27:17'),
(3, 4, 1, 1250.00, '2025-12-09', 'Efectivo', NULL, 'Pago al contado - Compra #000004', '2025-12-10 05:40:32'),
(4, 5, 1, 1750.00, '2025-12-09', 'Efectivo', NULL, 'Pago al contado - Compra #000004', '2025-12-10 05:40:32'),
(5, 6, 1, 1500.00, '2025-12-09', 'Efectivo', NULL, 'Pago al contado - Compra #000004', '2025-12-10 05:40:32'),
(6, 8, 1, 625.00, '2025-12-09', 'Efectivo', '', '', '2025-12-10 05:42:18'),
(7, 7, 1, 1500.00, '2025-12-10', 'Efectivo', '', '', '2025-12-10 06:04:50'),
(8, 4, 1, 200.00, '2025-12-10', 'Efectivo', NULL, NULL, '2025-12-10 21:28:53'),
(9, 6, 1, 150.00, '2025-12-11', 'Efectivo', NULL, NULL, '2025-12-10 21:28:53'),
(10, 9, 1, 500.00, '2025-12-11', 'Transferencia', NULL, NULL, '2025-12-10 21:28:53'),
(11, 17, 1, 1350.00, '2025-12-14', 'Efectivo', NULL, 'Pago al contado - Compra #000017', '2025-12-14 06:08:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_chica`
--

CREATE TABLE `caja_chica` (
  `id_movimiento` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `tipo_movimiento` enum('Ingreso','Egreso') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `categoria` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Ej: Compras, Servicios, Gastos Administrativos, Ventas',
  `concepto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `forma_pago` enum('Efectivo','Transferencia','Cheque','Tarjeta') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Efectivo',
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comprobante` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `caja_chica`
--

INSERT INTO `caja_chica` (`id_movimiento`, `id_usuario`, `id_sucursal`, `tipo_movimiento`, `categoria`, `concepto`, `monto`, `forma_pago`, `referencia`, `comprobante`, `fecha_movimiento`, `observaciones`, `created_at`) VALUES
(1, 1, 1, 'Egreso', 'Oficina', 'Compra material de oficina', 120.50, 'Efectivo', NULL, NULL, '2025-12-05', NULL, '2025-12-10 21:28:53'),
(2, 1, 1, 'Egreso', 'Servicios', 'Pago servicio de internet', 85.00, 'Transferencia', NULL, NULL, '2025-12-06', NULL, '2025-12-10 21:28:53'),
(3, 1, 1, 'Egreso', 'Mantenimiento', 'Mantenimiento equipo médico', 300.00, 'Efectivo', NULL, NULL, '2025-12-07', NULL, '2025-12-10 21:28:53'),
(4, 1, 1, 'Egreso', 'Limpieza', 'Compra insumos limpieza', 75.25, 'Efectivo', NULL, NULL, '2025-12-08', NULL, '2025-12-10 21:28:53'),
(5, 1, 1, 'Egreso', 'Servicios', 'Pago agua', 45.50, 'Efectivo', NULL, NULL, '2025-12-09', NULL, '2025-12-10 21:28:53'),
(6, 1, 1, 'Egreso', 'Transporte', 'Gasolina vehículo', 150.00, 'Efectivo', NULL, NULL, '2025-12-10', NULL, '2025-12-10 21:28:53'),
(7, 1, 1, 'Egreso', 'Laboratorio', 'Material de laboratorio', 220.75, 'Transferencia', NULL, NULL, '2025-12-11', NULL, '2025-12-10 21:28:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id_compra` int NOT NULL,
  `codigo_referencia` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_usuario` int NOT NULL,
  `nombre_medicamento` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `molecula` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `presentacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `casa_farmaceutica` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `fecha_compra` date NOT NULL,
  `tipo_factura` enum('Factura','Nota de Envío','Consumidor Final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Factura',
  `numero_factura` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo_pago` enum('Al Contado','Crédito 30 días','Crédito 60 días') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Al Contado',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `abonado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` enum('Pendiente','Abonado','Pagado','Entregado','Ingresado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_vencimiento` date DEFAULT NULL,
  `lote` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `compras`
--

INSERT INTO `compras` (`id_compra`, `codigo_referencia`, `id_usuario`, `nombre_medicamento`, `molecula`, `presentacion`, `casa_farmaceutica`, `cantidad`, `precio_unitario`, `precio_venta`, `fecha_compra`, `tipo_factura`, `numero_factura`, `tipo_pago`, `total`, `abonado`, `saldo`, `estado`, `fecha_vencimiento`, `lote`, `observaciones`, `created_at`, `updated_at`) VALUES
(1, '000001', 1, 'Aspek', 'Aspartato de Arginina', 'Caja de 15 flaconetes', 'Kaizen', 15, 150.00, 300.00, '2025-12-09', 'Nota de Envío', '10123545', 'Crédito 30 días', 2250.00, 0.00, 2250.00, 'Ingresado', '2027-08-01', '2025', '', '2025-12-10 04:50:34', '2025-12-10 04:54:24'),
(2, '000001', 1, 'Kaizezol', 'Pantoprazol', 'Caja de 30 cápsulas', 'Kaizen', 25, 130.00, 250.00, '2025-12-09', 'Nota de Envío', '10123545', 'Crédito 30 días', 3250.00, 2500.00, 750.00, 'Abonado', '2026-10-01', '2025', '', '2025-12-10 04:50:34', '2025-12-10 05:12:26'),
(3, '000003', 1, 'Ibuprofeno', 'Ibuprofeno', 'Capsulas', 'Infasa', 50, 15.00, 25.00, '2025-12-09', 'Consumidor Final', '', 'Crédito 30 días', 750.00, 750.00, 0.00, 'Pagado', '2027-07-01', '2025', '', '2025-12-10 05:19:35', '2025-12-10 05:27:17'),
(4, '000004', 1, 'Dolyprin', 'Desketoprofeno', 'Capsulas', 'Fake', 50, 25.00, 75.00, '2025-12-09', 'Factura', '100', 'Al Contado', 1250.00, 200.00, 1050.00, 'Ingresado', NULL, '', '', '2025-12-10 05:40:32', '2025-12-13 18:48:58'),
(5, '000004', 1, 'Bruzol', 'Ceftriaxona', 'Vial', 'Fake', 50, 35.00, 150.00, '2025-12-09', 'Factura', '100', 'Al Contado', 1750.00, 1750.00, 0.00, 'Ingresado', NULL, '', '', '2025-12-10 05:40:32', '2025-12-13 18:48:58'),
(6, '000004', 1, 'Amplioxima', 'Cefixima', 'Suspensión', 'Fake', 10, 150.00, 300.00, '2025-12-09', 'Factura', '100', 'Al Contado', 1500.00, 150.00, 1350.00, 'Ingresado', NULL, '', '', '2025-12-10 05:40:32', '2025-12-13 18:48:58'),
(7, '000007', 1, 'Paracetamol', 'Paracetamol', 'Jarabe', 'Prueba', 25, 125.00, 250.00, '2025-12-09', 'Consumidor Final', '', 'Crédito 30 días', 3125.00, 1500.00, 1625.00, 'Abonado', NULL, '', '', '2025-12-10 05:42:07', '2025-12-10 06:04:50'),
(8, '000007', 1, 'Ibuprofeno', 'Ibuprofeno', 'Jarabe', 'Prueba', 15, 100.00, 250.00, '2025-12-09', 'Consumidor Final', '', 'Crédito 30 días', 1500.00, 625.00, 875.00, 'Abonado', NULL, '', '', '2025-12-10 05:42:07', '2025-12-10 05:42:18'),
(9, 'CMP-20251205-001', 1, 'Paracetamol 500mg', 'Paracetamol', 'Tabletas x 20', 'Genfar', 100, 2.50, 5.50, '2025-12-05', 'Factura', NULL, 'Al Contado', 250.00, 500.00, -250.00, 'Abonado', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(10, 'CMP-20251205-001', 1, 'Amoxicilina 500mg', 'Amoxicilina', 'Cápsulas x 12', 'Genfar', 80, 4.50, 8.75, '2025-12-05', 'Factura', NULL, 'Al Contado', 360.00, 360.00, 0.00, 'Pagado', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(11, 'CMP-20251206-002', 1, 'Loratadina 10mg', 'Loratadina', 'Tabletas x 10', 'MK', 120, 3.25, 6.25, '2025-12-06', 'Factura', NULL, 'Crédito 30 días', 390.00, 0.00, 390.00, 'Pendiente', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(12, 'CMP-20251207-003', 1, 'Omeprazol 20mg', 'Omeprazol', 'Cápsulas x 14', 'Genfar', 90, 4.00, 7.80, '2025-12-07', 'Factura', NULL, 'Al Contado', 360.00, 360.00, 0.00, 'Pagado', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(13, 'CMP-20251208-004', 1, 'Ibuprofeno 400mg', 'Ibuprofeno', 'Tabletas x 20', 'Genfar', 110, 3.00, 6.50, '2025-12-08', 'Factura', NULL, 'Crédito 30 días', 330.00, 0.00, 330.00, 'Pendiente', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(14, 'CMP-20251209-005', 1, 'Metformina 850mg', 'Metformina', 'Tabletas x 30', 'Genfar', 60, 4.50, 9.25, '2025-12-09', 'Factura', NULL, 'Al Contado', 270.00, 270.00, 0.00, 'Pagado', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(15, 'CMP-20251210-006', 1, 'Losartán 50mg', 'Losartán', 'Tabletas x 30', 'Genfar', 70, 5.50, 10.50, '2025-12-10', 'Factura', NULL, 'Al Contado', 385.00, 385.00, 0.00, 'Pagado', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(16, 'CMP-20251211-007', 1, 'Salbutamol inhalador', 'Salbutamol', 'Inhalador 200 dosis', 'GSK', 40, 25.00, 45.00, '2025-12-11', 'Factura', NULL, 'Crédito 30 días', 1000.00, 0.00, 1000.00, 'Pendiente', NULL, NULL, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(17, '000017', 1, 'Dastyl C', 'Celecoxib 200mg', 'Caja de 10 Cápsulas', 'Kaizen', 30, 45.00, 150.00, '2025-12-14', 'Nota de Envío', '1521', 'Al Contado', 1350.00, 1350.00, 0.00, 'Ingresado', NULL, '', '', '2025-12-14 06:08:18', '2025-12-14 06:09:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedidos`
--

CREATE TABLE `detalle_pedidos` (
  `id_detalle_pedido` int NOT NULL,
  `id_pedido` int NOT NULL,
  `id_inventario` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id_detalle` int NOT NULL,
  `id_venta` int NOT NULL,
  `id_inventario` int NOT NULL,
  `tipo_factura_medicamento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id_detalle`, `id_venta`, `id_inventario`, `tipo_factura_medicamento`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 4, 14, NULL, 2, 9.25, 18.50),
(2, 4, 11, NULL, 3, 6.25, 18.75),
(3, 4, 9, NULL, 4, 5.50, 22.00),
(4, 5, 15, NULL, 3, 10.50, 31.50),
(5, 5, 10, NULL, 4, 8.75, 35.00),
(6, 5, 12, NULL, 5, 7.80, 39.00),
(7, 5, 13, NULL, 3, 6.50, 19.50),
(8, 19, 9, 'Factura', 20, 10.00, 200.00),
(9, 19, 13, 'Factura', 20, 10.00, 200.00),
(10, 20, 1, 'Nota de Envío', 10, 300.00, 3000.00),
(11, 21, 2, 'Nota de Envío', 1, 250.00, 250.00),
(12, 21, 1, 'Nota de Envío', 1, 300.00, 300.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `examenes`
--

CREATE TABLE `examenes` (
  `id_examen` int NOT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_usuario` int NOT NULL COMMENT 'Usuario que registró',
  `id_sucursal` int NOT NULL,
  `tipo_paciente` enum('Privado','EPS') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Privado',
  `examenes_realizados` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Lista de exámenes separados por coma',
  `tipo_pago` enum('Privado','Seguro Manual','EPS') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Privado',
  `monto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia','Cheque') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Efectivo',
  `numero_ticket` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Número de comprobante/ticket',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_examen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `examenes`
--

INSERT INTO `examenes` (`id_examen`, `id_paciente`, `nombre_paciente`, `id_usuario`, `id_sucursal`, `tipo_paciente`, `examenes_realizados`, `tipo_pago`, `monto`, `metodo_pago`, `numero_ticket`, `observaciones`, `fecha_examen`) VALUES
(2, 1, 'Samuel Ramírez', 1, 1, 'Privado', 'Electrocardiograma (ECG), Radiografía', 'Privado', 1000.00, 'Efectivo', 'EX-20251213-1608', '', '2025-12-13 16:24:39'),
(3, 12, 'Héctor Pineda', 1, 1, 'Privado', 'Electrocardiograma (ECG)', 'Privado', 300.00, 'Efectivo', 'EX-20251213-0126', '', '2025-12-13 18:55:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formas_pago`
--

CREATE TABLE `formas_pago` (
  `id_forma_pago` int NOT NULL,
  `id_venta` int NOT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta Débito','Tarjeta Crédito','Transferencia','Cheque','Seguro Médico') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `formas_pago`
--

INSERT INTO `formas_pago` (`id_forma_pago`, `id_venta`, `tipo_pago`, `monto`, `referencia`) VALUES
(1, 19, 'Efectivo', 400.00, NULL),
(2, 20, 'Efectivo', 3000.00, NULL),
(3, 21, 'Efectivo', 550.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id_inventario` int NOT NULL,
  `codigo_barras` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `codigo_referencia` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_compra` int DEFAULT NULL COMMENT 'Relación con la compra que originó este inventario',
  `nom_medicamento` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mol_medicamento` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `presentacion_med` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `casa_farmaceutica` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad_med` int NOT NULL DEFAULT '0',
  `estado_ingreso` enum('Pendiente','Ingresado','En Traslado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Ingresado',
  `precio_costo` decimal(10,2) DEFAULT NULL COMMENT 'Precio de compra/costo unitario',
  `precio_venta` decimal(10,2) DEFAULT NULL COMMENT 'Precio de venta al público',
  `id_sucursal` int NOT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `tipo_factura` enum('Factura','Nota de Envío','Consumidor Final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Factura',
  `numero_factura` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id_inventario`, `codigo_barras`, `codigo_referencia`, `id_compra`, `nom_medicamento`, `mol_medicamento`, `presentacion_med`, `casa_farmaceutica`, `cantidad_med`, `estado_ingreso`, `precio_costo`, `precio_venta`, `id_sucursal`, `fecha_adquisicion`, `fecha_vencimiento`, `tipo_factura`, `numero_factura`, `created_at`, `updated_at`) VALUES
(1, NULL, '000001', 1, 'Aspek', 'Aspartato de Arginina', 'Caja de 15 flaconetes', 'Kaizen', 2, 'Ingresado', 150.00, 300.00, 2, '2025-12-09', '2027-08-01', 'Nota de Envío', '10123545', '2025-12-10 04:50:34', '2025-12-30 01:18:35'),
(2, '7406313000394', '000001', 2, 'Kaizezol', 'Pantoprazol', 'Caja de 30 cápsulas', 'Kaizen', 24, 'Ingresado', 130.00, 250.00, 1, '2025-12-09', '2026-10-01', 'Nota de Envío', '10123545', '2025-12-10 04:50:34', '2025-12-14 06:06:49'),
(3, NULL, '000003', 3, 'Ibuprofeno', 'Ibuprofeno', 'Capsulas', 'Infasa', 50, 'Pendiente', 15.00, 25.00, 2, '2025-12-09', '2027-07-01', 'Consumidor Final', '', '2025-12-10 05:19:35', '2025-12-30 00:58:35'),
(4, NULL, '000004', 4, 'Dolyprin', 'Desketoprofeno', 'Capsulas', 'Fake', 10, 'Ingresado', 25.00, 75.00, 1, '2025-12-09', NULL, 'Factura', '100', '2025-12-10 05:40:32', '2025-12-30 01:15:18'),
(5, '', '000004', 5, 'Bruzol', 'Ceftriaxona', 'Vial', 'Fake', 50, 'Ingresado', 35.00, 150.00, 1, '2025-12-09', '2026-02-12', 'Consumidor Final', NULL, '2025-12-10 05:40:32', '2025-12-30 01:28:15'),
(6, NULL, '000004', 6, 'Amplioxima', 'Cefixima', 'Suspensión', 'Fake', 10, 'Ingresado', 150.00, 300.00, 1, '2025-12-09', NULL, 'Factura', '100', '2025-12-10 05:40:32', '2025-12-13 18:49:10'),
(7, NULL, '000007', 7, 'Paracetamol', 'Paracetamol', 'Jarabe', 'Prueba', 25, 'Pendiente', 125.00, 250.00, 2, '2025-12-09', NULL, 'Consumidor Final', '', '2025-12-10 05:42:07', '2025-12-30 00:59:47'),
(8, NULL, '000007', 8, 'Ibuprofeno', 'Ibuprofeno', 'Jarabe', 'Prueba', 15, 'Pendiente', 100.00, 250.00, 1, '2025-12-09', NULL, 'Consumidor Final', '', '2025-12-10 05:42:07', '2025-12-10 05:42:07'),
(9, NULL, 'INV-001', 2, 'Paracetamol 500mg', 'Paracetamol', 'Tabletas x 20', 'Genfar', 80, 'Ingresado', 2.50, 5.50, 1, '2025-12-05', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-13 15:21:09'),
(10, NULL, 'INV-002', 3, 'Amoxicilina 500mg', 'Amoxicilina', 'Cápsulas x 12', 'Genfar', 80, 'Ingresado', 4.50, 8.75, 2, '2025-12-05', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-30 00:59:50'),
(11, NULL, 'INV-003', 4, 'Loratadina 10mg', 'Loratadina', 'Tabletas x 10', 'MK', 120, 'Ingresado', 3.25, 6.25, 1, '2025-12-06', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(12, NULL, 'INV-004', 5, 'Omeprazol 20mg', 'Omeprazol', 'Cápsulas x 14', 'Genfar', 90, 'Ingresado', 4.00, 7.80, 1, '2025-12-07', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(13, NULL, 'INV-005', 6, 'Ibuprofeno 400mg', 'Ibuprofeno', 'Tabletas x 20', 'Genfar', 90, 'Ingresado', 3.00, 6.50, 1, '2025-12-08', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-13 15:21:09'),
(14, NULL, 'INV-006', 7, 'Metformina 850mg', 'Metformina', 'Tabletas x 30', 'Genfar', 60, 'Ingresado', 4.50, 9.25, 2, '2025-12-09', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-30 00:59:57'),
(15, NULL, 'INV-007', 8, 'Losartán 50mg', 'Losartán', 'Tabletas x 30', 'Genfar', 70, 'Ingresado', 5.50, 10.50, 1, '2025-12-10', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53'),
(16, NULL, 'INV-008', 9, 'Salbutamol inhalador', 'Salbutamol', 'Inhalador 200 dosis', 'GSK', 40, 'Ingresado', 25.00, 45.00, 2, '2025-12-11', NULL, 'Factura', NULL, '2025-12-10 21:28:53', '2025-12-30 00:59:54'),
(17, '3536dx2516', NULL, NULL, 'Dex-control', 'Vitaminas Neurotropas', 'Caja con 2 ampollas', 'TMH', 1, 'Ingresado', NULL, NULL, 1, '2025-12-14', '2027-01-01', 'Consumidor Final', NULL, '2025-12-14 06:04:30', '2025-12-14 06:04:30'),
(18, '7406313000356', '000017', 17, 'Dastyl C', 'Celecoxib 200mg', 'Caja de 10 Cápsulas', 'Kaizen', 30, 'Ingresado', 45.00, 150.00, 1, '2025-12-14', '2027-08-01', 'Nota de Envío', '1521', '2025-12-14 06:08:18', '2025-12-14 06:09:22'),
(19, '7401130000053', NULL, NULL, 'Panditop', 'Pancreatina / Dimetilpolisiloxano', 'Capsulas 200mg/80mg', 'Topfarma', 25, 'Ingresado', NULL, NULL, 1, '2025-12-15', '2026-01-02', 'Consumidor Final', NULL, '2025-12-15 18:55:50', '2025-12-15 18:55:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_cobro`
--

CREATE TABLE `ordenes_cobro` (
  `id_orden` int NOT NULL,
  `id_paciente` int NOT NULL,
  `id_medico` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro','EPS','IGS','MAWDY','PRIVADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Consulta',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `estado` enum('Pendiente','Pagado','Atendido') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_pago` timestamp NULL DEFAULT NULL,
  `necesita_farmacia` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ordenes_cobro`
--

INSERT INTO `ordenes_cobro` (`id_orden`, `id_paciente`, `id_medico`, `monto`, `tipo_cobro`, `descripcion`, `estado`, `fecha_creacion`, `fecha_pago`, `necesita_farmacia`) VALUES
(1, 1, 1, 150.00, 'Consulta', '', 'Atendido', '2025-12-13 16:33:17', '2025-12-13 17:11:14', 0),
(2, 5, 1, 125.00, 'Consulta', '', 'Atendido', '2025-12-13 16:34:55', '2025-12-13 17:09:40', 0),
(3, 2, 1, 100.00, 'IGS', '', 'Atendido', '2025-12-13 16:44:51', '2025-12-13 17:11:06', 0),
(4, 2, 1, 150.00, 'EPS', 'Pago de medicamento', 'Atendido', '2025-12-13 17:09:00', '2025-12-13 17:09:18', 0),
(5, 2, 1, 150.00, 'EPS', '', 'Atendido', '2025-12-13 18:46:29', '2025-12-13 18:55:28', 0),
(6, 12, 1, 300.00, 'PRIVADO', '', 'Atendido', '2025-12-13 18:54:22', '2025-12-13 18:54:48', 0),
(7, 5, 2, 300.00, 'Consulta', '', 'Atendido', '2025-12-14 02:44:15', '2025-12-14 02:44:15', 0),
(8, 12, 4, 300.00, 'Consulta', '', 'Atendido', '2025-12-14 02:44:55', '2025-12-14 02:44:55', 2),
(9, 13, 2, 300.00, 'Consulta', '', 'Atendido', '2025-12-14 17:30:12', '2025-12-14 17:30:12', 2),
(10, 13, 1, 150.00, 'PRIVADO', '', 'Pagado', '2025-12-14 17:31:57', '2025-12-14 17:32:14', 0),
(11, 5, 2, 300.00, 'Consulta', '', 'Atendido', '2025-12-15 18:48:45', '2025-12-15 18:48:45', 2),
(12, 12, 3, 1000.00, 'Consulta', '', 'Atendido', '2025-12-15 18:51:43', '2025-12-15 18:51:43', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id_paciente` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `genero` enum('Masculino','Femenino') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dpi` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo_paciente` enum('Privado','IGS','EPSS','Mawdy') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Privado',
  `consulta_domicilio` tinyint(1) DEFAULT '0' COMMENT '1 si requiere consulta a domicilio',
  `tiene_reconsulta_gratis` tinyint(1) DEFAULT '0' COMMENT '1 si tiene derecho a reconsulta gratis',
  `fecha_reconsulta_limite` date DEFAULT NULL COMMENT 'Fecha límite para reconsulta gratis',
  `id_medico` int DEFAULT NULL COMMENT 'Doctor asignado',
  `id_sucursal` int DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `motivo_consulta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `sintomas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `historia_clinica` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `medicacion_actual` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `alergias` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `historial_familiar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `estilo_vida` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `contacto_emergencia_nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contacto_emergencia_telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pacientes`
--

INSERT INTO `pacientes` (`id_paciente`, `nombre`, `apellido`, `fecha_nacimiento`, `genero`, `direccion`, `telefono`, `correo`, `dpi`, `tipo_paciente`, `consulta_domicilio`, `tiene_reconsulta_gratis`, `fecha_reconsulta_limite`, `id_medico`, `id_sucursal`, `observaciones`, `fecha_registro`, `ultima_actualizacion`, `motivo_consulta`, `sintomas`, `historia_clinica`, `medicacion_actual`, `alergias`, `historial_familiar`, `estilo_vida`, `contacto_emergencia_nombre`, `contacto_emergencia_telefono`) VALUES
(1, 'Samuel', 'Ramírez', '2000-08-25', 'Masculino', 'Chimusinique, Zona 12', '39029076', '', '3145577921301', 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 20:42:39', '2025-12-10 20:42:39', 'Dolor de cabeza', '', '', '', '', '', '', '', ''),
(2, 'María', 'González', '1985-05-15', 'Femenino', 'Zona 1, Huehuetenango', '11112222', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Juan', 'Pérez', '1978-08-22', 'Masculino', 'Zona 2, Huehuetenango', '22223333', NULL, NULL, 'IGS', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Carlos', 'Ramírez', '1990-11-30', 'Masculino', 'Zona 3, Huehuetenango', '33334444', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Ana', 'Torres', '1982-03-18', 'Femenino', 'Zona 4, Huehuetenango', '44445555', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-14 02:44:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Luis', 'Herrera', '1975-12-05', 'Masculino', 'Zona 5, Huehuetenango', '55556666', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Sofía', 'Mendoza', '1995-07-25', 'Femenino', 'Zona 6, Huehuetenango', '66667777', NULL, NULL, 'Mawdy', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Roberto', 'Díaz', '1988-09-12', 'Masculino', 'Zona 7, Huehuetenango', '77778888', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Elena', 'Castro', '1992-01-20', 'Femenino', 'Zona 8, Huehuetenango', '88889999', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Miguel', 'Ángel', '1972-04-08', 'Masculino', 'Zona 9, Huehuetenango', '99990000', NULL, NULL, 'IGS', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Patricia', 'Ruiz', '1986-06-14', 'Femenino', 'Zona 10, Huehuetenango', '00001111', NULL, NULL, 'Privado', 0, 0, NULL, NULL, 1, NULL, '2025-12-10 21:28:53', '2025-12-10 21:28:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'Héctor', 'Pineda', '1982-04-23', 'Masculino', '', '', '', '3145577921301', 'Privado', 0, 0, NULL, 4, 1, NULL, '2025-12-13 18:51:47', '2025-12-13 18:51:47', 'Dolor de pies', '', NULL, NULL, NULL, NULL, NULL, '', ''),
(13, 'Waldemar', 'Colindres', '2006-08-11', 'Masculino', '', '', '', '', 'Privado', 0, 0, NULL, 2, 1, NULL, '2025-12-14 17:29:20', '2025-12-14 17:29:20', 'No se te para', '', NULL, NULL, NULL, NULL, NULL, 'Waldemar Colindres', '12345678');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `cliente_nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cliente_telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cliente_direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tipo_documento` enum('Factura','Nota de Envío') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Nota de Envío',
  `tipo_pago_estado` enum('Al Contado','Crédito') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Al Contado',
  `metodo_pago` enum('Efectivo','Transferencia','COD') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Efectivo',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cargo_cod` decimal(10,2) DEFAULT '0.00' COMMENT 'Q26 + 3.5% si es COD',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado_pedido` enum('Pendiente','Procesando','Enviado','Entregado','Completado','Cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `estado_pago` enum('Pendiente','Pagado','Pendiente Crédito') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `id_venta` int DEFAULT NULL COMMENT 'Relación con venta generada',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_pedido` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procedimientos_menores`
--

CREATE TABLE `procedimientos_menores` (
  `id_procedimiento` int NOT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_usuario` int NOT NULL COMMENT 'Usuario que registró',
  `id_sucursal` int NOT NULL,
  `tipo_paciente` enum('Privado','EPS') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Privado',
  `procedimiento_realizado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tipo_pago` enum('Privado','Seguro Manual','EPS') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Privado',
  `monto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia','Cheque') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Efectivo',
  `numero_ticket` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Número de comprobante/ticket',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_procedimiento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `procedimientos_menores`
--

INSERT INTO `procedimientos_menores` (`id_procedimiento`, `id_paciente`, `nombre_paciente`, `id_usuario`, `id_sucursal`, `tipo_paciente`, `procedimiento_realizado`, `descripcion`, `tipo_pago`, `monto`, `metodo_pago`, `numero_ticket`, `observaciones`, `fecha_procedimiento`) VALUES
(2, 1, 'Samuel Ramírez', 1, 1, 'Privado', 'Curación de Heridas', '', 'Privado', 100.00, 'Efectivo', 'PM-20251213-1846', '', '2025-12-13 16:33:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas`
--

CREATE TABLE `recetas` (
  `id_receta` int NOT NULL,
  `id_orden` int NOT NULL,
  `id_medico` int NOT NULL,
  `detalle_json` text,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `recetas`
--

INSERT INTO `recetas` (`id_receta`, `id_orden`, `id_medico`, `detalle_json`, `fecha_creacion`) VALUES
(1, 2, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"2\",\"dosis\":\"1 Cada 8hr\\/24 d\\u00edas\"},{\"medicamento\":\"Aspek\",\"cantidad\":\"1\",\"dosis\":\"1 Flaconete en la ma\\u00f1ana \\/30 d\\u00edas\"}]', '2025-12-13 21:05:11'),
(2, 2, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"2\",\"dosis\":\"1 Cada 8hr\\/24 d\\u00edas\"},{\"medicamento\":\"Aspek\",\"cantidad\":\"1\",\"dosis\":\"1 Flaconete en la ma\\u00f1ana \\/30 d\\u00edas\"}]', '2025-12-13 21:05:19'),
(3, 2, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"2\",\"dosis\":\"1 Cada 8hr\\/24 d\\u00edas\"},{\"medicamento\":\"Aspek\",\"cantidad\":\"1\",\"dosis\":\"1 Flaconete en la ma\\u00f1ana \\/30 d\\u00edas\"}]', '2025-12-13 21:05:21'),
(4, 2, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"2\",\"dosis\":\"1 Cada 8hr\\/24 d\\u00edas\"},{\"medicamento\":\"Aspek\",\"cantidad\":\"1\",\"dosis\":\"1 Flaconete en la ma\\u00f1ana \\/30 d\\u00edas\"}]', '2025-12-13 21:05:24'),
(5, 3, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"1\",\"dosis\":\"1 cada 8hr\\/30 d\\u00edas\"},{\"medicamento\":\"Ibuprofeno\",\"cantidad\":\"2\",\"dosis\":\"1 cada 12hr\\/15 d\\u00edas\"}]', '2025-12-13 21:09:08'),
(6, 8, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"1\",\"dosis\":\"1 cada 8hr\\/30 d\\u00edas\"},{\"medicamento\":\"Kaizezol\",\"cantidad\":\"1\",\"dosis\":\"1 cada 8hr\\/30 d\\u00edas\"}]', '2025-12-13 21:19:05'),
(7, 9, 1, '[{\"medicamento\":\"Ibuprofeno\",\"cantidad\":\"1\",\"dosis\":\"1 cada 8hr\\/10 d\\u00edas\"},{\"medicamento\":\"Paracetamol\",\"cantidad\":\"1\",\"dosis\":\"1 cada 8hr\\/5 d\\u00edas\"}]', '2025-12-14 11:31:14'),
(8, 11, 1, '[{\"medicamento\":\"Kaizezol\",\"cantidad\":\"1\",\"dosis\":\"Cada 8 hr\"},{\"medicamento\":\"Ibuprofeno\",\"cantidad\":\"2\",\"dosis\":\"Cada 12 hr\"}]', '2025-12-15 12:50:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales`
--

CREATE TABLE `sucursales` (
  `id_sucursal` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sucursales`
--

INSERT INTO `sucursales` (`id_sucursal`, `nombre`, `direccion`, `telefono`, `activa`, `created_at`) VALUES
(1, 'Servimedic Terminal', 'Huehuetenango, zona 5', '50525292', 1, '2025-12-10 04:48:26'),
(2, 'Servimedic Chiantla', 'Salida hacia Huehuetenango, Chiantla', '34049600', 1, '2025-12-15 16:36:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transferencias`
--

CREATE TABLE `transferencias` (
  `id_transferencia` int NOT NULL,
  `id_inventario_origen` int NOT NULL,
  `id_inventario_destino` int NOT NULL,
  `id_sucursal_origen` int NOT NULL,
  `id_sucursal_destino` int NOT NULL,
  `cantidad` int NOT NULL,
  `id_usuario` int NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('En Camino','Recibido','Rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'En Camino'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rol` enum('Administrador','Doctor','Mayoreo','Farmacia') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_sucursal` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `usuario`, `password`, `telefono`, `rol`, `id_sucursal`, `created_at`, `updated_at`) VALUES
(1, 'Samuel', 'Ramírez', 'admin', 'admin', '39029076', 'Administrador', 1, '2025-12-10 04:49:01', '2025-12-10 04:49:01'),
(2, 'Aron', 'De León', 'draron', 'Servimedic', '53489634', 'Doctor', 1, '2025-12-10 21:28:53', '2025-12-15 18:43:00'),
(3, 'Jannya', 'Rivas', 'drajannya', 'Servimedic', '34049600', 'Doctor', 1, '2025-12-10 21:28:53', '2025-12-15 18:43:06'),
(4, 'Enrique', 'Pineda', 'SFenrique', 'Servimedic', '59167747', 'Administrador', 1, '2025-12-10 21:28:53', '2025-12-15 16:35:06'),
(5, 'Sonia', 'López', 'SFsonia', 'Servimedic', '50525292', 'Farmacia', 1, '2025-12-10 21:28:53', '2025-12-15 16:36:52'),
(6, 'Gabriela', 'Gomez', 'SFgabriela', 'Servimedic', '52148236', 'Mayoreo', 2, '2025-12-15 16:37:51', '2025-12-15 16:38:53'),
(7, 'Yeni', 'León', 'SFyeni', 'Servimedic', '35864417', 'Administrador', 2, '2025-12-15 16:41:00', '2025-12-15 16:41:00'),
(8, 'Jannya', 'Rivas', 'SFjannya', 'Servimedic', '34049600', 'Administrador', 1, '2025-12-15 18:45:07', '2025-12-15 18:45:07'),
(9, 'Aron', 'De León', 'SFaron', 'Servimedic', '53489634', 'Administrador', 1, '2025-12-15 18:45:07', '2025-12-15 18:45:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `cliente_nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cliente_nit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `venta_personal` tinyint(1) DEFAULT '0',
  `tipo_documento` enum('Factura','Nota de Envío','Recibo de Venta') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Recibo de Venta' COMMENT 'Tipo de documento de venta',
  `es_credito` tinyint(1) DEFAULT '0' COMMENT '1 si es venta a crédito (desde pedidos)',
  `id_pedido` int DEFAULT NULL COMMENT 'Relación con pedido si aplica',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(10,2) DEFAULT '0.00',
  `total_final` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` enum('Pendiente','Completada','Cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Completada',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_venta` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `id_usuario`, `id_sucursal`, `cliente_nombre`, `cliente_nit`, `venta_personal`, `tipo_documento`, `es_credito`, `id_pedido`, `total`, `descuento`, `total_final`, `estado`, `observaciones`, `fecha_venta`) VALUES
(1, 3, 1, 'Cliente 1', NULL, 0, 'Recibo de Venta', 0, NULL, 45.75, 0.00, 45.75, 'Completada', NULL, '2025-12-05 15:45:00'),
(2, 3, 1, 'Cliente 2', NULL, 0, 'Recibo de Venta', 0, NULL, 120.50, 0.00, 120.50, 'Completada', NULL, '2025-12-05 17:20:00'),
(3, 3, 1, 'Cliente 3', NULL, 0, 'Recibo de Venta', 0, NULL, 85.25, 0.00, 85.25, 'Completada', NULL, '2025-12-05 20:15:00'),
(4, 3, 1, 'Cliente 4', NULL, 0, 'Recibo de Venta', 0, NULL, 210.00, 0.00, 210.00, 'Completada', NULL, '2025-12-05 22:30:00'),
(5, 3, 1, 'Cliente 5', NULL, 0, 'Recibo de Venta', 0, NULL, 65.25, 0.00, 65.25, 'Completada', NULL, '2025-12-06 16:30:00'),
(6, 3, 1, 'Cliente 6', NULL, 0, 'Recibo de Venta', 0, NULL, 180.75, 0.00, 180.75, 'Completada', NULL, '2025-12-06 21:45:00'),
(7, 3, 1, 'Cliente 7', NULL, 0, 'Recibo de Venta', 0, NULL, 95.50, 0.00, 95.50, 'Completada', NULL, '2025-12-07 17:15:00'),
(8, 3, 1, 'Cliente 8', NULL, 0, 'Recibo de Venta', 0, NULL, 220.00, 0.00, 220.00, 'Completada', NULL, '2025-12-07 23:20:00'),
(9, 3, 1, 'Cliente 9', NULL, 0, 'Recibo de Venta', 0, NULL, 45.00, 0.00, 45.00, 'Completada', NULL, '2025-12-08 18:00:00'),
(10, 3, 1, 'Cliente 10', NULL, 0, 'Recibo de Venta', 0, NULL, 125.75, 0.00, 125.75, 'Completada', NULL, '2025-12-08 22:30:00'),
(11, 3, 1, 'Cliente 11', NULL, 0, 'Recibo de Venta', 0, NULL, 150.25, 0.00, 150.25, 'Completada', NULL, '2025-12-09 15:30:00'),
(12, 3, 1, 'Cliente 12', NULL, 0, 'Recibo de Venta', 0, NULL, 85.75, 0.00, 85.75, 'Completada', NULL, '2025-12-09 20:15:00'),
(13, 3, 1, 'Cliente 13', NULL, 0, 'Recibo de Venta', 0, NULL, 320.50, 0.00, 320.50, 'Completada', NULL, '2025-12-10 00:00:00'),
(14, 3, 1, 'Cliente 14', NULL, 0, 'Recibo de Venta', 0, NULL, 110.00, 0.00, 110.00, 'Completada', NULL, '2025-12-10 16:45:00'),
(15, 3, 1, 'Cliente 15', NULL, 0, 'Recibo de Venta', 0, NULL, 195.25, 0.00, 195.25, 'Completada', NULL, '2025-12-10 22:30:00'),
(16, 3, 1, 'Cliente 16', NULL, 0, 'Recibo de Venta', 0, NULL, 75.50, 0.00, 75.50, 'Completada', NULL, '2025-12-11 14:15:00'),
(17, 3, 1, 'Cliente 17', NULL, 0, 'Recibo de Venta', 0, NULL, 160.75, 0.00, 160.75, 'Completada', NULL, '2025-12-11 19:45:00'),
(18, 3, 1, 'Cliente 18', NULL, 0, 'Recibo de Venta', 0, NULL, 280.00, 0.00, 280.00, 'Completada', NULL, '2025-12-11 23:50:00'),
(19, 1, 1, 'Samuel Ramírez', 'CF', 0, 'Recibo de Venta', 0, NULL, 400.00, 0.00, 400.00, 'Completada', NULL, '2025-12-13 15:21:09'),
(20, 1, 1, 'Oscar Ramirez', 'CF', 0, 'Recibo de Venta', 0, NULL, 3000.00, 0.00, 3000.00, 'Completada', NULL, '2025-12-13 16:39:01'),
(21, 1, 1, 'Héctor Pineda', 'CF', 0, 'Recibo de Venta', 0, NULL, 550.00, 0.00, 550.00, 'Completada', NULL, '2025-12-14 04:22:58');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `abonos_compras`
--
ALTER TABLE `abonos_compras`
  ADD PRIMARY KEY (`id_abono`),
  ADD KEY `fk_abono_compra` (`id_compra`),
  ADD KEY `fk_abono_usuario` (`id_usuario`);

--
-- Indices de la tabla `caja_chica`
--
ALTER TABLE `caja_chica`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `fk_caja_usuario` (`id_usuario`),
  ADD KEY `fk_caja_sucursal` (`id_sucursal`),
  ADD KEY `idx_tipo_movimiento` (`tipo_movimiento`),
  ADD KEY `idx_fecha_movimiento` (`fecha_movimiento`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `fk_compra_usuario` (`id_usuario`),
  ADD KEY `idx_fecha_compra` (`fecha_compra`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD PRIMARY KEY (`id_detalle_pedido`),
  ADD KEY `fk_detalle_pedido` (`id_pedido`),
  ADD KEY `fk_detalle_pedido_inventario` (`id_inventario`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_detalle_venta` (`id_venta`),
  ADD KEY `fk_detalle_inventario` (`id_inventario`);

--
-- Indices de la tabla `examenes`
--
ALTER TABLE `examenes`
  ADD PRIMARY KEY (`id_examen`),
  ADD KEY `fk_examen_paciente` (`id_paciente`),
  ADD KEY `fk_examen_usuario` (`id_usuario`),
  ADD KEY `fk_examen_sucursal` (`id_sucursal`),
  ADD KEY `idx_tipo_paciente_examen` (`tipo_paciente`),
  ADD KEY `idx_tipo_pago_examen` (`tipo_pago`),
  ADD KEY `idx_fecha_examen` (`fecha_examen`);

--
-- Indices de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  ADD PRIMARY KEY (`id_forma_pago`),
  ADD KEY `fk_pago_venta` (`id_venta`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id_inventario`),
  ADD KEY `fk_inventario_sucursal` (`id_sucursal`),
  ADD KEY `fk_inventario_compra` (`id_compra`),
  ADD KEY `idx_estado_ingreso` (`estado_ingreso`),
  ADD KEY `idx_fecha_vencimiento` (`fecha_vencimiento`),
  ADD KEY `idx_codigo_barras` (`codigo_barras`);

--
-- Indices de la tabla `ordenes_cobro`
--
ALTER TABLE `ordenes_cobro`
  ADD PRIMARY KEY (`id_orden`),
  ADD KEY `fk_orden_paciente` (`id_paciente`),
  ADD KEY `fk_orden_medico` (`id_medico`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_paciente`),
  ADD KEY `fk_paciente_medico` (`id_medico`),
  ADD KEY `fk_paciente_sucursal` (`id_sucursal`),
  ADD KEY `idx_nombre_apellido` (`nombre`,`apellido`),
  ADD KEY `idx_tipo_paciente` (`tipo_paciente`),
  ADD KEY `idx_tiene_reconsulta` (`tiene_reconsulta_gratis`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `fk_pedido_usuario` (`id_usuario`),
  ADD KEY `fk_pedido_sucursal` (`id_sucursal`),
  ADD KEY `fk_pedido_venta` (`id_venta`),
  ADD KEY `idx_estado_pedido` (`estado_pedido`),
  ADD KEY `idx_estado_pago` (`estado_pago`);

--
-- Indices de la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  ADD PRIMARY KEY (`id_procedimiento`),
  ADD KEY `fk_procedimiento_paciente` (`id_paciente`),
  ADD KEY `fk_procedimiento_usuario` (`id_usuario`),
  ADD KEY `fk_procedimiento_sucursal` (`id_sucursal`),
  ADD KEY `idx_tipo_paciente_proc` (`tipo_paciente`),
  ADD KEY `idx_tipo_pago_proc` (`tipo_pago`),
  ADD KEY `idx_fecha_procedimiento` (`fecha_procedimiento`);

--
-- Indices de la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD PRIMARY KEY (`id_receta`);

--
-- Indices de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id_sucursal`);

--
-- Indices de la tabla `transferencias`
--
ALTER TABLE `transferencias`
  ADD PRIMARY KEY (`id_transferencia`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `idx_usuario` (`usuario`),
  ADD KEY `fk_usuario_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `fk_venta_usuario` (`id_usuario`),
  ADD KEY `fk_venta_sucursal` (`id_sucursal`),
  ADD KEY `idx_fecha_venta` (`fecha_venta`),
  ADD KEY `idx_venta_personal` (`venta_personal`),
  ADD KEY `idx_tipo_documento` (`tipo_documento`),
  ADD KEY `idx_es_credito` (`es_credito`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `abonos_compras`
--
ALTER TABLE `abonos_compras`
  MODIFY `id_abono` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `caja_chica`
--
ALTER TABLE `caja_chica`
  MODIFY `id_movimiento` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id_compra` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  MODIFY `id_detalle_pedido` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `examenes`
--
ALTER TABLE `examenes`
  MODIFY `id_examen` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  MODIFY `id_forma_pago` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_inventario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `ordenes_cobro`
--
ALTER TABLE `ordenes_cobro`
  MODIFY `id_orden` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  MODIFY `id_procedimiento` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `recetas`
--
ALTER TABLE `recetas`
  MODIFY `id_receta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id_sucursal` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `transferencias`
--
ALTER TABLE `transferencias`
  MODIFY `id_transferencia` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `abonos_compras`
--
ALTER TABLE `abonos_compras`
  ADD CONSTRAINT `fk_abono_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_abono_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `caja_chica`
--
ALTER TABLE `caja_chica`
  ADD CONSTRAINT `fk_caja_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_pedido_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `fk_detalle_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_detalle_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `examenes`
--
ALTER TABLE `examenes`
  ADD CONSTRAINT `fk_examen_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_examen_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_examen_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  ADD CONSTRAINT `fk_pago_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `fk_inventario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD CONSTRAINT `fk_paciente_medico` FOREIGN KEY (`id_medico`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_paciente_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedido_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  ADD CONSTRAINT `fk_procedimiento_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_procedimiento_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_procedimiento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
