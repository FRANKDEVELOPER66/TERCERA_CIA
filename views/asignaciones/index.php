<style>
    /* ========================================
       ✨ BOTÓN FLOTANTE HORIZONTAL
       ======================================== */
    .fab-button {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        min-width: 300px;
        height: 60px;
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-shadow: 0 8px 25px rgba(45, 80, 22, 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 1000;
        border: none;
        color: white;
        font-size: 1rem;
        font-weight: 600;
        padding: 0 30px;
    }

    .fab-button:hover {
        transform: translateX(-50%) translateY(-5px);
        box-shadow: 0 12px 35px rgba(45, 80, 22, 0.6);
        background: linear-gradient(135deg, #3d6b1f 0%, #4a8025 100%);
    }

    .fab-button:active {
        transform: translateX(-50%) translateY(-2px);
    }

    .fab-button i {
        font-size: 1.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    /* ========================================
       🎨 ESTILOS PARA COMISIONES
       ======================================== */

    /* Botón flotante de comisiones */
    .btn-comision-flotante {
        position: fixed;
        bottom: 100px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 999;
        border: none;
        color: white;
        font-size: 1.5rem;
    }

    .btn-comision-flotante:hover {
        transform: translateY(-5px) scale(1.1);
        box-shadow: 0 12px 35px rgba(243, 156, 18, 0.6);
    }

    .btn-comision-flotante i {
        animation: plane-fly 2s infinite;
    }

    @keyframes plane-fly {

        0%,
        100% {
            transform: translateX(0);
        }

        50% {
            transform: translateX(5px);
        }
    }

    /* Servicios afectados */
    #listaServiciosAfectados .servicio-item {
        padding: 10px;
        background: rgba(243, 156, 18, 0.1);
        border-left: 4px solid #f39c12;
        margin-bottom: 8px;
        border-radius: 4px;
    }

    /* Tabla de comisiones activas */
    .tabla-comisiones {
        width: 100%;
    }

    .tabla-comisiones thead {
        background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        color: white;
    }

    .tabla-comisiones th {
        padding: 12px;
        font-weight: 600;
        text-align: center;
    }

    .tabla-comisiones td {
        padding: 12px;
        vertical-align: middle;
        text-align: center;
    }

    .tabla-comisiones tbody tr:hover {
        background: #fef5e7;
    }

    .badge-comision-activa {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        padding: 6px 15px;
        border-radius: 20px;
        font-weight: 600;
    }

    .badge-reemplazos {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.85rem;
    }

    /* Card de compensación */
    .compensacion-card {
        background: linear-gradient(135deg, #ecf9f2 0%, #d5f4e6 100%);
        border-left: 4px solid #27ae60;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .compensacion-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
    }

    .compensacion-card .badge {
        font-size: 0.9rem;
    }

    /* Indicador de comisión en servicios */
    .personnel-item.en-comision {
        background: linear-gradient(135deg, rgba(243, 156, 18, 0.2) 0%, rgba(230, 126, 34, 0.2) 100%);
        border-left: 4px solid #f39c12;
        position: relative;
    }

    .personnel-item.en-comision::before {
        content: "✈️";
        position: absolute;
        left: -25px;
        font-size: 1.2rem;
    }

    .personnel-item.reemplazo {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.2) 0%, rgba(41, 128, 185, 0.2) 100%);
        border-left: 4px solid #3498db;
        position: relative;
    }

    .personnel-item.reemplazo::before {
        content: "↻";
        position: absolute;
        left: -25px;
        font-size: 1.2rem;
        color: #3498db;
    }

    /* ========================================
       ✨ TABLA DE HISTORIAL DE CICLOS
       ======================================== */
    .tabla-historial {
        width: 100%;
        margin-top: 1rem;
    }

    .tabla-historial thead {
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        color: white;
    }

    .tabla-historial th {
        padding: 15px;
        font-weight: 600;
        text-align: center;
        border: none;
    }

    .tabla-historial tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #e2e8f0;
    }

    .tabla-historial tbody tr:hover {
        background: #f8f9fa;
        transform: scale(1.01);
    }

    .tabla-historial td {
        padding: 15px;
        vertical-align: middle;
        text-align: center;
    }

    .numero-ciclo {
        font-weight: 700;
        font-size: 1.2rem;
        color: #2d5016;
        width: 80px;
    }

    .fechas-ciclo {
        font-size: 1rem;
        color: #334155;
    }

    .estado-badge {
        padding: 6px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-block;
    }

    .estado-badge.activo {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .estado-badge.finalizado {
        background: #e2e8f0;
        color: #64748b;
    }

    .btn-consultar-ciclo {
        background: linear-gradient(135deg, #4c84ff 0%, #667eea 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
    }

    .btn-consultar-ciclo:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(76, 132, 255, 0.4);
        background: linear-gradient(135deg, #667eea 0%, #4c84ff 100%);
    }

    .empty-historial {
        text-align: center;
        padding: 3rem;
        color: #94a3b8;
    }

    .empty-historial i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    /* ========================================
       ESTILOS EXISTENTES (continuación)
       ======================================== */
    .calendar-widget {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    }

    .week-selector {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-bottom: 2rem;
    }

    .service-card {
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        color: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .service-card.semana {
        background: linear-gradient(135deg, #ff9966 0%, #ff5e62 100%);
    }

    .official-day-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        margin-top: 0.5rem;
        font-size: 0.95rem;
        box-shadow: 0 2px 8px rgba(45, 80, 22, 0.3);
    }

    .official-day-badge i {
        font-size: 1.1rem;
    }

    .official-badge {
        background: rgba(255, 255, 255, 0.3);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .official-badge i {
        margin-right: 0.5rem;
    }

    .service-card h4 {
        margin: 0 0 1rem 0;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .service-card.tactico {
        background: #c85a28;
    }

    .service-card.reconocimiento {
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
    }

    .service-card.nocturno,
    .service-card.servicionocturno {
        background: linear-gradient(135deg, #1a472a 0%, #2d5f3d 100%);
    }

    .service-card.banderin {
        background: linear-gradient(135deg, #b8540f 0%, #d96c2f 100%);
    }

    .service-card.tacticotropa {
        background: #d4763b;
    }

    .service-card.cuartelero {
        background: #652900ff;
    }

    .personnel-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .personnel-item {
        background: rgba(255, 255, 255, 0.2);
        padding: 0.75rem 1rem;
        border-radius: 10px;
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .week-service-card {
        background: linear-gradient(135deg, #c85a28 0%, #b8540f 100%);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 5px 25px rgba(200, 90, 40, 0.3);
        margin-bottom: 2rem;
    }

    .week-title {
        color: white;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .semana-card {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .semana-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        color: white;
    }

    .semana-header h4 {
        margin: 0;
        font-weight: 700;
    }

    .semana-content {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 10px;
        padding: 1rem;
    }

    .personnel-item-semana {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .personnel-item-semana strong {
        color: #ff5e62;
    }

    .day-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        border-left: 5px solid #667eea;
    }

    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .day-header h3 {
        margin: 0;
        color: #1a1a1a;
        font-weight: 700;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        display: none;
    }

    .loading-spinner {
        text-align: center;
        color: white;
    }

    .spinner-border {
        width: 4rem;
        height: 4rem;
    }

    /* ✨ BOTONES */
    .btn-generate {
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(45, 80, 22, 0.3);
    }

    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(45, 80, 22, 0.4);
        background: linear-gradient(135deg, #3d6b1f 0%, #4a8025 100%);
    }

    .btn-consult {
        background: linear-gradient(135deg, #4c84ff 0%, #667eea 100%);
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-consult:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76, 132, 255, 0.4);
        background: linear-gradient(135deg, #667eea 0%, #4c84ff 100%);
    }

    .btn-delete-cycle {
        background: linear-gradient(135deg, #804d00ff 0%, #ff0f0fff 100%);
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(214, 52, 71, 0.3);
    }

    .btn-delete-cycle:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(183, 21, 64, 0.4);
    }

    .btn-export-pdf {
        background: linear-gradient(135deg, #ff6b6b 0%, #c92a2a 100%);
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
    }

    .btn-export-pdf:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(201, 42, 42, 0.4);
    }

    .btn-back {
        background: linear-gradient(135deg, #868f96 0%, #596164 100%);
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(89, 97, 100, 0.3);
    }

    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(89, 97, 100, 0.4);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #718096;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    /* Estilos para botones toggle */
    .btn-grupo {
        padding: 12px 20px;
        font-weight: 600;
        border-width: 2px;
        transition: all 0.3s ease;
        position: relative;
    }

    .btn-grupo i {
        margin-right: 5px;
        display: none;
    }

    .btn-grupo.active i {
        display: inline;
    }

    .btn-grupo.active {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-grupo:not(.active) {
        opacity: 0.5;
    }

    .btn-grupo:hover {
        transform: scale(1.05);
    }

    .grupo-categoria {
        border-left: 4px solid transparent;
        padding-left: 10px;
        transition: all 0.3s ease;
    }

    .grupo-categoria:hover {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
    }

    /* Botones de preset */
    .preset-btn {
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .preset-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="page-header">
        <h1>
            <i class="bi bi-calendar-range"></i>
            Generador de Servicios (Ciclos de 10 Días)
        </h1>
    </div>

    <!-- Controles -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="calendar-widget">
                <div class="week-selector">
                    <div class="flex-grow-1">
                        <label for="fechaInicio" class="form-label fw-bold">
                            <i class="bi bi-calendar-event"></i> Seleccionar Fecha de Inicio del Ciclo
                        </label>
                        <input
                            type="date"
                            id="fechaInicio"
                            class="form-control form-control-lg"
                            placeholder="Seleccione una fecha">

                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Seleccione cualquier fecha disponible para iniciar el ciclo de 10 días
                        </small>
                    </div>

                    <div class="d-flex gap-2 align-items-end">
                        <!-- ✨ Botón Generar -->
                        <button id="btnGenerar" class="btn btn-generate" style="display: none;">
                            <i class="bi bi-lightning-charge-fill"></i> Generar Ciclo
                        </button>

                        <!-- ✨ Botón Consultar -->
                        <button id="btnConsultar" class="btn btn-consult" style="display: none;">
                            <i class="bi bi-search"></i> Consultar
                        </button>

                        <!-- ✨ Botón Eliminar Ciclo -->
                        <button id="btnEliminarCiclo" class="btn btn-delete-cycle" style="display: none;">
                            <i class="bi bi-trash"></i> Eliminar Ciclo
                        </button>

                        <!-- ✨ Botón Exportar PDF -->
                        <button id="btnExportarPDF" class="btn btn-export-pdf" style="display: none;">
                            <i class="bi bi-file-pdf-fill"></i> Exportar PDF
                        </button>

                        <!-- ✨ Botón Regresar -->
                        <button id="btnRegresar" class="btn btn-back" style="display: none;">
                            <i class="bi bi-arrow-left-circle"></i> Regresar
                        </button>
                    </div>
                </div>

                <!-- 🆕 Botones de Comisiones -->
                <div class="d-flex gap-2 justify-content-end mt-3">
                    <button type="button" class="btn btn-warning" id="btnVerComisionesActivas">
                        <i class="bi bi-airplane"></i> Comisiones Activas
                    </button>
                    <button type="button" class="btn btn-success" id="btnVerCompensaciones">
                        <i class="bi bi-gift"></i> Compensaciones
                    </button>

                    <!-- 🆕 NUEVO BOTÓN PARA REGISTRAR -->
                    <button type="button" class="btn btn-orange" id="btnRegistrarComisionDirecto"
                        style="background: #f39c12; color: white; font-weight: 600;">
                        <i class="bi bi-airplane-fill"></i> Registrar Comisión
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de Resultados -->
    <div class="row">
        <div class="col-12">
            <div id="contenedorResultados">
                <div class="empty-state">
                    <i class="bi bi-calendar-check"></i>
                    <h3>Selecciona una fecha de inicio</h3>
                    <p>Elige cualquier día y genera un ciclo de 10 días de servicios</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ✨ BOTÓN FLOTANTE HORIZONTAL (Historial) -->
    <button class="fab-button" id="fabHistorial">
        <i class="bi bi-clock-history"></i>
        <span>Consultar Ciclos Generados</span>
    </button>

    <!-- 🆕 BOTÓN FLOTANTE DE COMISIONES -->
    <button class="btn-comision-flotante" id="btnComisionFlotante" title="Registrar Comisión">
        <i class="bi bi-airplane-fill"></i>
    </button>

    <!-- ✨ MODAL DE HISTORIAL CON TABLA -->
    <div class="modal fade" id="modalHistorialCiclos" tabindex="-1" aria-labelledby="modalHistorialCiclosLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%); color: white;">
                    <h5 class="modal-title" id="modalHistorialCiclosLabel">
                        <i class="bi bi-clock-history"></i> Historial de Ciclos Generados
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" id="contenedorHistorial">
                    <!-- Se llenará dinámicamente -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3">Cargando ciclos...</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ✨ MODAL DE SELECCIÓN DE GRUPOS -->
    <div class="modal fade" id="modalSeleccionGrupos" tabindex="-1" aria-labelledby="modalSeleccionGruposLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%); color: white;">
                    <h5 class="modal-title" id="modalSeleccionGruposLabel">
                        <i class="bi bi-calendar-range"></i> Seleccionar Grupos Disponibles
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Fecha seleccionada -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Ciclo a generar (10 días):</strong> <span id="fechaCicloModal">-</span>
                    </div>

                    <!-- ✨ PRESETS DE ROTACIÓN RÁPIDA -->
                    <div class="alert alert-primary">
                        <h6><i class="bi bi-lightning-fill"></i> Selección Rápida:</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-primary preset-btn" data-preset="todos">
                                <i class="bi bi-check-all"></i> Todos Disponibles
                            </button>
                            <button type="button" class="btn btn-sm btn-warning preset-btn" data-preset="rotacion_a">
                                <i class="bi bi-arrow-repeat"></i> Grupo A Descanso
                            </button>
                            <button type="button" class="btn btn-sm btn-warning preset-btn" data-preset="rotacion_b">
                                <i class="bi bi-arrow-repeat"></i> Grupo B Descanso
                            </button>
                            <button type="button" class="btn btn-sm btn-warning preset-btn" data-preset="rotacion_c">
                                <i class="bi bi-arrow-repeat"></i> Grupo C Descanso
                            </button>
                            <button type="button" class="btn btn-sm btn-danger preset-btn" data-preset="ninguno">
                                <i class="bi bi-x-circle"></i> Desmarcar Todos
                            </button>
                        </div>
                    </div>

                    <!-- Instrucciones -->
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Importante:</strong> Selecciona los grupos que estarán <strong>DISPONIBLES</strong> para servicios durante los 10 días.
                        Los grupos desmarcados estarán de descanso.
                    </div>

                    <!-- OFICIALES -->
                    <div class="grupo-categoria mb-4">
                        <div class="grupo-header" style="background: #9B59B6; color: white; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                            <h6 class="mb-0">
                                <i class="bi bi-star-fill"></i> OFICIALES
                            </h6>
                        </div>
                        <div class="btn-group-toggle d-flex gap-2" data-toggle="buttons">
                            <button type="button" class="btn btn-outline-primary btn-grupo active flex-fill" data-grupo="1">
                                <i class="bi bi-check-circle-fill"></i> Grupo A
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-grupo active flex-fill" data-grupo="2">
                                <i class="bi bi-check-circle-fill"></i> Grupo B
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-grupo active flex-fill" data-grupo="3">
                                <i class="bi bi-check-circle-fill"></i> Grupo C
                            </button>
                        </div>
                    </div>

                    <!-- ESPECIALISTAS -->
                    <div class="grupo-categoria mb-4">
                        <div class="grupo-header" style="background: #ff6b6b; color: white; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                            <h6 class="mb-0">
                                <i class="bi bi-tools"></i> ESPECIALISTAS
                            </h6>
                        </div>
                        <div class="btn-group-toggle d-flex gap-2" data-toggle="buttons">
                            <button type="button" class="btn btn-outline-danger btn-grupo active flex-fill" data-grupo="4">
                                <i class="bi bi-check-circle-fill"></i> Grupo A
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-grupo active flex-fill" data-grupo="5">
                                <i class="bi bi-check-circle-fill"></i> Grupo B
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-grupo active flex-fill" data-grupo="6">
                                <i class="bi bi-check-circle-fill"></i> Grupo C
                            </button>
                        </div>
                    </div>

                    <!-- TROPA -->
                    <div class="grupo-categoria mb-4">
                        <div class="grupo-header" style="background: #4ECDC4; color: white; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                            <h6 class="mb-0">
                                <i class="bi bi-people-fill"></i> TROPA
                            </h6>
                        </div>
                        <div class="btn-group-toggle d-flex gap-2" data-toggle="buttons">
                            <button type="button" class="btn btn-outline-info btn-grupo active flex-fill" data-grupo="7">
                                <i class="bi bi-check-circle-fill"></i> Grupo A
                            </button>
                            <button type="button" class="btn btn-outline-info btn-grupo active flex-fill" data-grupo="8">
                                <i class="bi bi-check-circle-fill"></i> Grupo B
                            </button>
                            <button type="button" class="btn btn-outline-info btn-grupo active flex-fill" data-grupo="9">
                                <i class="bi bi-check-circle-fill"></i> Grupo C
                            </button>
                        </div>
                    </div>

                    <!-- Resumen -->
                    <div class="alert alert-success" id="resumenSeleccion" style="display: none;">
                        <strong><i class="bi bi-check-circle"></i> Personal disponible:</strong>
                        <div id="conteoPersonal" class="mt-2"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-generate" id="btnConfirmarGeneracion">
                        <i class="bi bi-lightning-charge-fill"></i> Generar Ciclo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================
         🆕 MODAL DE COMISIONES OFICIALES
         ======================================== -->
    <div class="modal fade" id="modalComision" tabindex="-1" aria-labelledby="modalComisionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white;">
                    <h5 class="modal-title" id="modalComisionLabel">
                        <i class="bi bi-airplane-fill"></i> Registrar Comisión Oficial
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Alerta informativa -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <strong>Sistema de Reemplazos Inteligente:</strong>
                        <ul class="mb-0 mt-2">
                            <li>El sistema buscará automáticamente reemplazos siguiendo criterios de equidad</li>
                            <li>Se priorizará a personal con menos servicios y que no trabajó ayer</li>
                            <li>Quien reemplace ganará prioridad para futuros descansos</li>
                            <li>Se respetarán los descansos programados</li>
                        </ul>
                    </div>

                    <form id="formComision">
                        <!-- Selección de Personal -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-person-fill"></i> Personal a Comisionar *
                            </label>
                            <select class="form-select form-select-lg" id="personalComision" required>
                                <option value="">Seleccione el personal...</option>
                                <!-- Se llena dinámicamente -->
                            </select>
                            <small class="text-muted">Personal activo disponible</small>
                        </div>

                        <!-- Fechas -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-calendar-event"></i> Fecha Inicio *
                                </label>
                                <input type="date" class="form-control form-control-lg" id="fechaInicioComision" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-calendar-check"></i> Fecha Fin *
                                </label>
                                <input type="date" class="form-control form-control-lg" id="fechaFinComision" required>
                            </div>
                        </div>

                        <!-- Destino -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-geo-alt-fill"></i> Destino
                            </label>
                            <input type="text" class="form-control" id="destinoComision"
                                value="Ciudad Capital" placeholder="Ciudad Capital">
                            <small class="text-muted">Lugar de la comisión</small>
                        </div>

                        <!-- Número de Oficio -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-file-earmark-text-fill"></i> Número de Oficio/Documento *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="numeroOficio"
                                placeholder="Ej: 123-2025-BHR" required>
                            <small class="text-muted">
                                <i class="bi bi-shield-fill-check"></i>
                                <strong>Requerido para trazabilidad</strong> - Debe ser único
                            </small>
                        </div>

                        <!-- Motivo -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-chat-left-text-fill"></i> Motivo (Opcional)
                            </label>
                            <textarea class="form-control" id="motivoComision" rows="3"
                                placeholder="Ej: Envío de correspondencia a Ciudad Capital"></textarea>
                        </div>

                        <!-- Vista previa de servicios afectados -->
                        <div id="serviciosAfectadosPreview" style="display: none;">
                            <div class="alert alert-warning">
                                <h6 class="alert-heading">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    Servicios que serán reemplazados:
                                </h6>
                                <hr>
                                <div id="listaServiciosAfectados" class="mb-0"></div>
                            </div>
                        </div>

                        <!-- Resumen de días -->
                        <div id="resumenDiasComision" style="display: none;">
                            <div class="alert alert-primary">
                                <strong><i class="bi bi-calendar-range"></i> Duración total:</strong>
                                <span id="totalDiasComision" class="fs-5 fw-bold"></span> días
                                <div class="mt-2" id="alertaCompensacion" style="display: none;">
                                    <i class="bi bi-gift-fill"></i>
                                    <small>
                                        <strong>Comisión larga (10+ días):</strong>
                                        El personal recibirá prioridad adicional al regresar
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-warning btn-lg" id="btnRegistrarComision">
                        <i class="bi bi-check-circle-fill"></i> Registrar Comisión
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================
         🆕 MODAL DE COMISIONES ACTIVAS
         ======================================== -->
    <div class="modal fade" id="modalComisionesActivas" tabindex="-1" aria-labelledby="modalComisionesActivasLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); color: white;">
                    <h5 class="modal-title" id="modalComisionesActivasLabel">
                        <i class="bi bi-airplane"></i> Comisiones Activas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div id="contenedorComisionesActivas">
                        <!-- Se llena dinámicamente -->
                        <div class="text-center py-4">
                            <div class="spinner-border text-warning" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3">Cargando comisiones activas...</p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================
         🆕 MODAL DE PERSONAL CON COMPENSACIONES
         ======================================== -->
    <div class="modal fade" id="modalCompensaciones" tabindex="-1" aria-labelledby="modalCompensacionesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white;">
                    <h5 class="modal-title" id="modalCompensacionesLabel">
                        <i class="bi bi-gift-fill"></i> Personal con Compensaciones Pendientes
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="bi bi-info-circle-fill"></i>
                        <strong>Sistema de Compensaciones:</strong>
                        <p class="mb-0">
                            El personal que reemplaza a otros en comisión recibe <strong>prioridad automática</strong>
                            para futuros servicios. Esto asegura equidad en la carga de trabajo.
                        </p>
                    </div>

                    <div id="contenedorCompensaciones">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner">
        <div class="spinner-border text-light" role="status"></div>
        <h4 class="mt-3">Generando servicios del ciclo...</h4>
        <p>Por favor espere, esto puede tomar unos momentos</p>
        <small class="text-muted">Generando 10 días de asignaciones</small>
    </div>
</div>

<script src="build/js/asignaciones/index.js" type="module"></script>