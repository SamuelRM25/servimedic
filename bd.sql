-- MySQL dump 10.13  Distrib 8.0.44, for macos11.7 (x86_64)
--
-- Host: bkzonlznatzzfkelstum-mysql.services.clever-cloud.com    Database: bkzonlznatzzfkelstum
-- ------------------------------------------------------
-- Server version	8.0.22-13

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `abonos_compras`
--

DROP TABLE IF EXISTS `abonos_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `abonos_compras` (
  `id_abono` int NOT NULL AUTO_INCREMENT,
  `id_compra` int NOT NULL,
  `id_usuario` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_abono` date NOT NULL,
  `forma_pago` enum('Efectivo','Tarjeta Débito','Tarjeta Crédito','Transferencia','Cheque') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_abono`),
  KEY `fk_abono_compra` (`id_compra`),
  KEY `fk_abono_usuario` (`id_usuario`),
  CONSTRAINT `fk_abono_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  CONSTRAINT `fk_abono_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `abonos_compras`
--

LOCK TABLES `abonos_compras` WRITE;
/*!40000 ALTER TABLE `abonos_compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `abonos_compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caja_chica`
--

DROP TABLE IF EXISTS `caja_chica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caja_chica` (
  `id_movimiento` int NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_movimiento`),
  KEY `fk_caja_usuario` (`id_usuario`),
  KEY `fk_caja_sucursal` (`id_sucursal`),
  KEY `idx_tipo_movimiento` (`tipo_movimiento`),
  KEY `idx_fecha_movimiento` (`fecha_movimiento`),
  CONSTRAINT `fk_caja_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caja_chica`
--

LOCK TABLES `caja_chica` WRITE;
/*!40000 ALTER TABLE `caja_chica` DISABLE KEYS */;
/*!40000 ALTER TABLE `caja_chica` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_general_ci,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cliente`),
  KEY `idx_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `id_compra` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_compra`),
  KEY `fk_compra_usuario` (`id_usuario`),
  KEY `idx_fecha_compra` (`fecha_compra`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_pedidos`
--

DROP TABLE IF EXISTS `detalle_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_pedidos` (
  `id_detalle_pedido` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_inventario` int DEFAULT NULL,
  `codigo_item` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle_pedido`),
  KEY `fk_detalle_pedido` (`id_pedido`),
  KEY `fk_detalle_pedido_inventario` (`id_inventario`),
  CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_pedido_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_pedidos`
--

LOCK TABLES `detalle_pedidos` WRITE;
/*!40000 ALTER TABLE `detalle_pedidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_ventas`
--

DROP TABLE IF EXISTS `detalle_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_ventas` (
  `id_detalle` int NOT NULL AUTO_INCREMENT,
  `id_venta` int NOT NULL,
  `id_inventario` int NOT NULL,
  `tipo_factura_medicamento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detalle_venta` (`id_venta`),
  KEY `fk_detalle_inventario` (`id_inventario`),
  CONSTRAINT `fk_detalle_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE RESTRICT,
  CONSTRAINT `fk_detalle_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_ventas`
--

LOCK TABLES `detalle_ventas` WRITE;
/*!40000 ALTER TABLE `detalle_ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `formas_pago`
--

DROP TABLE IF EXISTS `formas_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas_pago` (
  `id_forma_pago` int NOT NULL AUTO_INCREMENT,
  `id_venta` int NOT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta Débito','Tarjeta Crédito','Transferencia','Cheque','Seguro Médico') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_forma_pago`),
  KEY `fk_pago_venta` (`id_venta`),
  CONSTRAINT `fk_pago_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formas_pago`
--

LOCK TABLES `formas_pago` WRITE;
/*!40000 ALTER TABLE `formas_pago` DISABLE KEYS */;
/*!40000 ALTER TABLE `formas_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventario`
--

DROP TABLE IF EXISTS `inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario` (
  `id_inventario` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_inventario`),
  UNIQUE KEY `uq_codigo_referencia` (`codigo_referencia`),
  KEY `fk_inventario_sucursal` (`id_sucursal`),
  KEY `fk_inventario_compra` (`id_compra`),
  KEY `idx_estado_ingreso` (`estado_ingreso`),
  KEY `idx_fecha_vencimiento` (`fecha_vencimiento`),
  KEY `idx_codigo_barras` (`codigo_barras`),
  CONSTRAINT `fk_inventario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventario`
--

LOCK TABLES `inventario` WRITE;
/*!40000 ALTER TABLE `inventario` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_cobro`
--

DROP TABLE IF EXISTS `ordenes_cobro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes_cobro` (
  `id_orden` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `id_medico` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro','EPS','IGS','MAWDY','PRIVADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Consulta',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `estado` enum('Pendiente','Pagado','Atendido') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_pago` timestamp NULL DEFAULT NULL,
  `necesita_farmacia` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_orden`),
  KEY `fk_orden_paciente` (`id_cliente`),
  KEY `fk_orden_medico` (`id_medico`),
  CONSTRAINT `fk_orden_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_cobro`
--

LOCK TABLES `ordenes_cobro` WRITE;
/*!40000 ALTER TABLE `ordenes_cobro` DISABLE KEYS */;
/*!40000 ALTER TABLE `ordenes_cobro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id_pedido` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `es_cotizacion` tinyint(1) NOT NULL DEFAULT '0',
  `cotizacion_numero` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cliente_nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cliente_telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cliente_direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `condiciones_pago` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo_documento` enum('Factura','Nota de Envío') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Nota de Envío',
  `tipo_pago_estado` enum('Al Contado','Crédito') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Al Contado',
  `metodo_pago` enum('Efectivo','Transferencia','COD','Tarjeta') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Efectivo',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cargo_cod` decimal(10,2) DEFAULT '0.00' COMMENT 'Q26 + 3.5% si es COD',
  `subtotal_original` decimal(10,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado_pedido` enum('Pendiente','Procesando','Enviado','Entregado','Completado','Cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `estado_pago` enum('Pendiente','Pagado','Pendiente Crédito') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `id_venta` int DEFAULT NULL COMMENT 'Relación con venta generada',
  `cotizacion_origen_id` int DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_pedido` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega` date DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `fk_pedido_usuario` (`id_usuario`),
  KEY `fk_pedido_sucursal` (`id_sucursal`),
  KEY `fk_pedido_venta` (`id_venta`),
  KEY `idx_estado_pedido` (`estado_pedido`),
  KEY `idx_estado_pago` (`estado_pago`),
  KEY `idx_cotizacion` (`es_cotizacion`,`estado_pedido`),
  KEY `idx_cot_numero` (`cotizacion_numero`),
  CONSTRAINT `fk_pedido_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recetas`
--

DROP TABLE IF EXISTS `recetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recetas` (
  `id_receta` int NOT NULL AUTO_INCREMENT,
  `id_orden` int NOT NULL,
  `id_medico` int NOT NULL,
  `detalle_json` text,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_receta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recetas`
--

LOCK TABLES `recetas` WRITE;
/*!40000 ALTER TABLE `recetas` DISABLE KEYS */;
/*!40000 ALTER TABLE `recetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursales`
--

DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursales` (
  `id_sucursal` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion_pdf` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono2` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `banco` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cuenta_tipo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cuenta_numero` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `texto_legal_isr` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'SUJETO A PAGOS TRIMESTRALES ISR',
  `texto_legal_iva` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'EXENTA DEL IVA (art. 7 núm. 15 Ley del IVA)',
  PRIMARY KEY (`id_sucursal`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursales`
--

LOCK TABLES `sucursales` WRITE;
/*!40000 ALTER TABLE `sucursales` DISABLE KEYS */;
INSERT INTO `sucursales` VALUES (1,'CLINOVA Terminal','Huehuetenango, zona 5','7MA. AV 7-25 ZONA 1, HUEHUETENANGO','3404-9600','5915-2494','BANRURAL','MONETARIA','3141054428',1,'2025-12-10 04:48:26','SUJETO A PAGOS TRIMESTRALES ISR','EXENTA DEL IVA (art. 7 núm. 15 Ley del IVA)'),(2,'CLINOVA Chiantla','Salida hacia Huehuetenango, Chiantla',NULL,'34049600',NULL,NULL,NULL,NULL,1,'2025-12-15 16:36:14','SUJETO A PAGOS TRIMESTRALES ISR','EXENTA DEL IVA (art. 7 núm. 15 Ley del IVA)');
/*!40000 ALTER TABLE `sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transferencias`
--

DROP TABLE IF EXISTS `transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transferencias` (
  `id_transferencia` int NOT NULL AUTO_INCREMENT,
  `id_inventario_origen` int NOT NULL,
  `id_inventario_destino` int NOT NULL,
  `id_sucursal_origen` int NOT NULL,
  `id_sucursal_destino` int NOT NULL,
  `cantidad` int NOT NULL,
  `id_usuario` int NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('En Camino','Recibido','Rechazado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'En Camino',
  PRIMARY KEY (`id_transferencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transferencias`
--

LOCK TABLES `transferencias` WRITE;
/*!40000 ALTER TABLE `transferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `transferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rol` enum('Administrador','Doctor','Mayoreo','Farmacia') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_sucursal` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `idx_usuario` (`usuario`),
  KEY `fk_usuario_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_usuario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Samuel','Ramírez','admin','admin','39029076','Administrador',1,'2025-12-10 04:49:01','2025-12-10 04:49:01'),(2,'Aron','De León','draron','CLINOVA','53489634','Doctor',1,'2025-12-10 21:28:53','2026-10-10 00:32:38'),(3,'Jannya','Rivas','drajannya','CLINOVA','34049600','Doctor',1,'2025-12-10 21:28:53','2026-10-10 00:32:38'),(4,'Enrique','Pineda','SFenrique','CLINOVA','59167747','Administrador',1,'2025-12-10 21:28:53','2026-10-10 00:32:38'),(5,'Sonia','López','SFsonia','CLINOVA','50525292','Farmacia',1,'2025-12-10 21:28:53','2026-10-10 00:32:38'),(6,'Gabriela','Gomez','SFgabriela','CLINOVA','52148236','Mayoreo',2,'2025-12-15 16:37:51','2026-10-10 00:32:38'),(7,'Yeni','León','SFyeni','CLINOVA','35864417','Administrador',2,'2025-12-15 16:41:00','2026-10-10 00:32:38'),(8,'Jannya','Rivas','SFjannya','CLINOVA','34049600','Administrador',1,'2025-12-15 18:45:07','2026-10-10 00:32:38'),(9,'Aron','De León','SFaron','CLINOVA','53489634','Administrador',1,'2025-12-15 18:45:07','2026-10-10 00:32:38');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas` (
  `id_venta` int NOT NULL AUTO_INCREMENT,
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
  `fecha_venta` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_venta`),
  KEY `fk_venta_usuario` (`id_usuario`),
  KEY `fk_venta_sucursal` (`id_sucursal`),
  KEY `idx_fecha_venta` (`fecha_venta`),
  KEY `idx_venta_personal` (`venta_personal`),
  KEY `idx_tipo_documento` (`tipo_documento`),
  KEY `idx_es_credito` (`es_credito`),
  CONSTRAINT `fk_venta_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT,
  CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-13 21:27:32
