-- ============================================
-- SERVIMEDIC - BASE DE DATOS COMPLETA
-- Versión 2.0 - Integración Total
-- Fecha: 2025-11-20
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar todas las tablas si existen
DROP TABLE IF EXISTS `caja_chica`;
DROP TABLE IF EXISTS `detalle_pedidos`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `procedimientos_menores`;
DROP TABLE IF EXISTS `examenes`;
DROP TABLE IF EXISTS `abonos_compras`;
DROP TABLE IF EXISTS `formas_pago`;
DROP TABLE IF EXISTS `detalle_ventas`;
DROP TABLE IF EXISTS `ventas`;
DROP TABLE IF EXISTS `inventario`;
DROP TABLE IF EXISTS `compras`;
DROP TABLE IF EXISTS `pacientes`;
DROP TABLE IF EXISTS `sucursales`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- TABLA DE USUARIOS
-- ============================================
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('Administrador','Doctor','Secretaria','Farmacia') NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE SUCURSALES
-- ============================================
CREATE TABLE `sucursales` (
  `id_sucursal` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE PACIENTES
-- ============================================
CREATE TABLE `pacientes` (
  `id_paciente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `genero` enum('Masculino','Femenino') NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `dpi` varchar(20) DEFAULT NULL,
  `tipo_paciente` enum('Privado','IGS','EPSS','Mawdy') NOT NULL DEFAULT 'Privado',
  `consulta_domicilio` tinyint(1) DEFAULT 0 COMMENT '1 si requiere consulta a domicilio',
  `tiene_reconsulta_gratis` tinyint(1) DEFAULT 0 COMMENT '1 si tiene derecho a reconsulta gratis',
  `fecha_reconsulta_limite` date DEFAULT NULL COMMENT 'Fecha límite para reconsulta gratis',
  `id_medico` int(11) DEFAULT NULL COMMENT 'Doctor asignado',
  `id_sucursal` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_paciente`),
  KEY `fk_paciente_medico` (`id_medico`),
  KEY `fk_paciente_sucursal` (`id_sucursal`),
  KEY `idx_nombre_apellido` (`nombre`, `apellido`),
  KEY `idx_tipo_paciente` (`tipo_paciente`),
  KEY `idx_tiene_reconsulta` (`tiene_reconsulta_gratis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE INVENTARIO (con integración de compras)
-- ============================================
CREATE TABLE `inventario` (
  `id_inventario` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_referencia` varchar(20) DEFAULT NULL,
  `id_compra` int(11) DEFAULT NULL COMMENT 'Relación con la compra que originó este inventario',
  `nom_medicamento` varchar(200) NOT NULL,
  `mol_medicamento` varchar(200) NOT NULL,
  `presentacion_med` varchar(100) NOT NULL,
  `casa_farmaceutica` varchar(100) NOT NULL,
  `cantidad_med` int(11) NOT NULL DEFAULT 0,
  `estado_ingreso` enum('Pendiente','Ingresado') NOT NULL DEFAULT 'Ingresado',
  `precio_costo` decimal(10,2) DEFAULT NULL COMMENT 'Precio de compra/costo unitario',
  `precio_venta` decimal(10,2) DEFAULT NULL COMMENT 'Precio de venta al público',
  `id_sucursal` int(11) NOT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `tipo_factura` enum('Factura','Nota de Envío','Consumidor Final') DEFAULT 'Factura',
  `numero_factura` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_inventario`),
  KEY `fk_inventario_sucursal` (`id_sucursal`),
  KEY `fk_inventario_compra` (`id_compra`),
  KEY `idx_estado_ingreso` (`estado_ingreso`),
  KEY `idx_fecha_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE COMPRAS
-- ============================================
CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_referencia` varchar(20) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_medicamento` varchar(200) NOT NULL,
  `molecula` varchar(200) NOT NULL,
  `presentacion` varchar(100) NOT NULL,
  `casa_farmaceutica` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `fecha_compra` date NOT NULL,
  `tipo_factura` enum('Factura','Nota de Envío','Consumidor Final') NOT NULL DEFAULT 'Factura',
  `numero_factura` varchar(50) DEFAULT NULL,
  `tipo_pago` enum('Al Contado','Crédito 30 días','Crédito 60 días') NOT NULL DEFAULT 'Al Contado',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `abonado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` enum('Pendiente','Abonado','Pagado','Entregado','Ingresado') NOT NULL DEFAULT 'Pendiente',
  `fecha_vencimiento` date DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_compra`),
  KEY `fk_compra_usuario` (`id_usuario`),
  KEY `idx_fecha_compra` (`fecha_compra`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE ABONOS A COMPRAS
-- ============================================
CREATE TABLE `abonos_compras` (
  `id_abono` int(11) NOT NULL AUTO_INCREMENT,
  `id_compra` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_abono` date NOT NULL,
  `forma_pago` enum('Efectivo','Tarjeta Débito','Tarjeta Crédito','Transferencia','Cheque') NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_abono`),
  KEY `fk_abono_compra` (`id_compra`),
  KEY `fk_abono_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE VENTAS
-- ============================================
CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `cliente_nombre` varchar(200) NOT NULL,
  `cliente_nit` varchar(20) DEFAULT NULL,
  `venta_personal` tinyint(1) DEFAULT 0,
  `tipo_documento` enum('Factura','Nota de Envío','Recibo de Venta') DEFAULT 'Recibo de Venta' COMMENT 'Tipo de documento de venta',
  `es_credito` tinyint(1) DEFAULT 0 COMMENT '1 si es venta a crédito (desde pedidos)',
  `id_pedido` int(11) DEFAULT NULL COMMENT 'Relación con pedido si aplica',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `total_final` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` enum('Pendiente','Completada','Cancelada') NOT NULL DEFAULT 'Completada',
  `observaciones` text DEFAULT NULL,
  `fecha_venta` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_venta`),
  KEY `fk_venta_usuario` (`id_usuario`),
  KEY `fk_venta_sucursal` (`id_sucursal`),
  KEY `idx_fecha_venta` (`fecha_venta`),
  KEY `idx_venta_personal` (`venta_personal`),
  KEY `idx_tipo_documento` (`tipo_documento`),
  KEY `idx_es_credito` (`es_credito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE DETALLE DE VENTAS
-- ============================================
CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `id_inventario` int(11) NOT NULL,
  `tipo_factura_medicamento` varchar(50) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detalle_venta` (`id_venta`),
  KEY `fk_detalle_inventario` (`id_inventario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE FORMAS DE PAGO
-- ============================================
CREATE TABLE `formas_pago` (
  `id_forma_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta Débito','Tarjeta Crédito','Transferencia','Cheque','Seguro Médico') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_forma_pago`),
  KEY `fk_pago_venta` (`id_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE PEDIDOS (Ventas por mayor)
-- ============================================
CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `cliente_nombre` varchar(200) NOT NULL,
  `cliente_telefono` varchar(20) DEFAULT NULL,
  `cliente_direccion` text DEFAULT NULL,
  `tipo_documento` enum('Factura','Nota de Envío') NOT NULL DEFAULT 'Nota de Envío',
  `tipo_pago_estado` enum('Al Contado','Crédito') NOT NULL DEFAULT 'Al Contado',
  `metodo_pago` enum('Efectivo','Transferencia','COD') NOT NULL DEFAULT 'Efectivo',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cargo_cod` decimal(10,2) DEFAULT 0.00 COMMENT 'Q26 + 3.5% si es COD',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado_pedido` enum('Pendiente','Procesando','Enviado','Entregado','Completado','Cancelado') NOT NULL DEFAULT 'Pendiente',
  `estado_pago` enum('Pendiente','Pagado','Pendiente Crédito') NOT NULL DEFAULT 'Pendiente',
  `id_venta` int(11) DEFAULT NULL COMMENT 'Relación con venta generada',
  `observaciones` text DEFAULT NULL,
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_entrega` date DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `fk_pedido_usuario` (`id_usuario`),
  KEY `fk_pedido_sucursal` (`id_sucursal`),
  KEY `fk_pedido_venta` (`id_venta`),
  KEY `idx_estado_pedido` (`estado_pedido`),
  KEY `idx_estado_pago` (`estado_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE DETALLE DE PEDIDOS
-- ============================================
CREATE TABLE `detalle_pedidos` (
  `id_detalle_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_inventario` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle_pedido`),
  KEY `fk_detalle_pedido` (`id_pedido`),
  KEY `fk_detalle_pedido_inventario` (`id_inventario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE CAJA CHICA
-- ============================================
CREATE TABLE `caja_chica` (
  `id_movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `tipo_movimiento` enum('Ingreso','Egreso') NOT NULL,
  `categoria` varchar(100) NOT NULL COMMENT 'Ej: Compras, Servicios, Gastos Administrativos, Ventas',
  `concepto` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `forma_pago` enum('Efectivo','Transferencia','Cheque','Tarjeta') NOT NULL DEFAULT 'Efectivo',
  `referencia` varchar(100) DEFAULT NULL,
  `comprobante` varchar(100) DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_movimiento`),
  KEY `fk_caja_usuario` (`id_usuario`),
  KEY `fk_caja_sucursal` (`id_sucursal`),
  KEY `idx_tipo_movimiento` (`tipo_movimiento`),
  KEY `idx_fecha_movimiento` (`fecha_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE EXÁMENES
-- ============================================
CREATE TABLE `examenes` (
  `id_examen` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) NOT NULL,
  `nombre_paciente` varchar(200) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que registró',
  `id_sucursal` int(11) NOT NULL,
  `tipo_paciente` enum('Privado','EPS') NOT NULL DEFAULT 'Privado',
  `examenes_realizados` text NOT NULL COMMENT 'Lista de exámenes separados por coma',
  `tipo_pago` enum('Privado','Seguro Manual','EPS') NOT NULL DEFAULT 'Privado',
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia','Cheque') DEFAULT 'Efectivo',
  `numero_ticket` varchar(50) DEFAULT NULL COMMENT 'Número de comprobante/ticket',
  `observaciones` text DEFAULT NULL,
  `fecha_examen` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_examen`),
  KEY `fk_examen_paciente` (`id_paciente`),
  KEY `fk_examen_usuario` (`id_usuario`),
  KEY `fk_examen_sucursal` (`id_sucursal`),
  KEY `idx_tipo_paciente_examen` (`tipo_paciente`),
  KEY `idx_tipo_pago_examen` (`tipo_pago`),
  KEY `idx_fecha_examen` (`fecha_examen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLA DE PROCEDIMIENTOS MENORES
-- ============================================
CREATE TABLE `procedimientos_menores` (
  `id_procedimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) NOT NULL,
  `nombre_paciente` varchar(200) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que registró',
  `id_sucursal` int(11) NOT NULL,
  `tipo_paciente` enum('Privado','EPS') NOT NULL DEFAULT 'Privado',
  `procedimiento_realizado` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_pago` enum('Privado','Seguro Manual','EPS') NOT NULL DEFAULT 'Privado',
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia','Cheque') DEFAULT 'Efectivo',
  `numero_ticket` varchar(50) DEFAULT NULL COMMENT 'Número de comprobante/ticket',
  `observaciones` text DEFAULT NULL,
  `fecha_procedimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_procedimiento`),
  KEY `fk_procedimiento_paciente` (`id_paciente`),
  KEY `fk_procedimiento_usuario` (`id_usuario`),
  KEY `fk_procedimiento_sucursal` (`id_sucursal`),
  KEY `idx_tipo_paciente_proc` (`tipo_paciente`),
  KEY `idx_tipo_pago_proc` (`tipo_pago`),
  KEY `idx_fecha_procedimiento` (`fecha_procedimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- AGREGAR CONSTRAINTS DE LLAVES FORÁNEAS
-- ============================================
ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL;
ALTER TABLE `pacientes` ADD CONSTRAINT `fk_paciente_medico` FOREIGN KEY (`id_medico`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
ALTER TABLE `pacientes` ADD CONSTRAINT `fk_paciente_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL;
ALTER TABLE `inventario` ADD CONSTRAINT `fk_inventario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;
ALTER TABLE `compras` ADD CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `abonos_compras` ADD CONSTRAINT `fk_abono_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE;
ALTER TABLE `abonos_compras` ADD CONSTRAINT `fk_abono_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `ventas` ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `ventas` ADD CONSTRAINT `fk_venta_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;
ALTER TABLE `detalle_ventas` ADD CONSTRAINT `fk_detalle_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE;
ALTER TABLE `detalle_ventas` ADD CONSTRAINT `fk_detalle_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE RESTRICT;
ALTER TABLE `formas_pago` ADD CONSTRAINT `fk_pago_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE;
ALTER TABLE `pedidos` ADD CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `pedidos` ADD CONSTRAINT `fk_pedido_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;
ALTER TABLE `detalle_pedidos` ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE;
ALTER TABLE `detalle_pedidos` ADD CONSTRAINT `fk_detalle_pedido_inventario` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE RESTRICT;
ALTER TABLE `caja_chica` ADD CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `caja_chica` ADD CONSTRAINT `fk_caja_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;
ALTER TABLE `examenes` ADD CONSTRAINT `fk_examen_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT;
ALTER TABLE `examenes` ADD CONSTRAINT `fk_examen_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `examenes` ADD CONSTRAINT `fk_examen_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;
ALTER TABLE `procedimientos_menores` ADD CONSTRAINT `fk_procedimiento_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT;
ALTER TABLE `procedimientos_menores` ADD CONSTRAINT `fk_procedimiento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;
ALTER TABLE `procedimientos_menores` ADD CONSTRAINT `fk_procedimiento_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE RESTRICT;

-- ============================================
-- FIN DEL SCRIPT DE ESTRUCTURA
-- Para datos de ejemplo, ejecutar ejemplos.sql
-- ============================================
