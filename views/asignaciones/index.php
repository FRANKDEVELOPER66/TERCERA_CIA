<style>
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
        /* Verde oscuro por defecto */
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
        /* Verde oscuro */
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
        background: linear-gradient(135deg, #c85a28 0%, #d96c2f 100%);
        /* Naranja oscuro */
    }

    .service-card.reconocimiento {
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        /* Verde oscuro */
    }

    .service-card.nocturno {
        background: linear-gradient(135deg, #1a472a 0%, #2d5f3d 100%);
        /* Verde muy oscuro */
    }

    .service-card.banderin {
        background: linear-gradient(135deg, #b8540f 0%, #d96c2f 100%);
        /* Naranja medio */
    }

    .service-card.cuartelero {
        background: linear-gradient(135deg, #3d6b1f 0%, #508a25 100%);
        /* Verde medio */
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
        /* Naranja oscuro */
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

    .btn-generate {
        background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
        /* Verde oscuro */
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
    }

    .btn-delete-week {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    }

    .btn-export-pdf {
    background: linear-gradient(135deg, #c85a28 0%, #b8540f 100%); /* Naranja oscuro */
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
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="page-header">
        <h1>
            <i class="bi bi-calendar-week"></i>
            Generador de Servicios Semanales
        </h1>
    </div>

    <!-- Controles -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="calendar-widget">
                <div class="week-selector">
                    <div class="flex-grow-1">
                        <label for="fechaInicio" class="form-label fw-bold">
                            <i class="bi bi-calendar-event"></i> Seleccionar Semana (Lunes)
                        </label>
                        <input type="date" id="fechaInicio" class="form-control form-control-lg">
                        <small class="text-muted">Debe seleccionar un día LUNES para iniciar la semana</small>
                    </div>
                    <div class="d-flex gap-2 align-items-end">
                        <button id="btnGenerar" class="btn btn-generate">
                            <i class="bi bi-lightning-charge-fill"></i> Generar Servicios
                        </button>
                        <button id="btnConsultar" class="btn btn-primary-custom">
                            <i class="bi bi-search"></i> Consultar
                        </button>
                        <button id="btnEliminarSemana" class="btn btn-delete-week" style="display: none;">
                            <i class="bi bi-trash"></i> Eliminar Semana
                        </button>
                        <button id="btnExportarPDF" class="btn btn-export-pdf" style="display: none;">
                            <i class="bi bi-file-pdf"></i> Exportar PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de Resultados -->
    <div id="contenedorResultados">
        <div class="empty-state">
            <i class="bi bi-calendar-check"></i>
            <h3>Selecciona una semana</h3>
            <p>Elige un lunes y genera los servicios para visualizarlos aquí</p>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner">
        <div class="spinner-border text-light" role="status"></div>
        <h4 class="mt-3">Generando servicios...</h4>
        <p>Por favor espere</p>
    </div>
</div>

<script src="build/js/asignaciones/index.js" type="module"></script>