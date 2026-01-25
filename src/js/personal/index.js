import { Dropdown } from "bootstrap";
import { Toast, validarFormulario } from "../funciones";
import Swal from "sweetalert2";
import DataTable from "datatables.net-bs5";
import { lenguaje } from "../lenguaje";

const formulario = document.getElementById('formularioPersonal');
const tabla = document.getElementById('tablaPersonal');
const btnGuardar = document.getElementById('btnGuardar');
const btnModificar = document.getElementById('btnModificar');
const btnCancelar = document.getElementById('btnCancelar');
const btnFlotante = document.getElementById('btnFlotante');
const contenedorFormulario = document.getElementById('contenedorFormulario');
const contenedorTabla = document.getElementById('contenedorTabla');
const tituloFormulario = document.getElementById('tituloFormulario');

let contador = 1;

const datatable = new DataTable('#tablaPersonal', {
    language: lenguaje,
    pageLength: '15',
    lengthMenu: [3, 9, 11, 25, 100],
    columns: [
        {
            title: 'No.',
            data: 'id_personal',
            width: '2%',
            render: (data, type, row, meta) => {
                return meta.row + 1;
            }
        },
        {
            title: 'Nombres',
            data: 'nombres',
            width: '15%',
        },
        {
            title: 'Apellidos',
            data: 'apellidos',
            width: '15%',
        },
        {
            title: 'Tipo',
            data: 'tipo',
            width: '10%',
        },
        {
            title: 'Grado',
            data: 'grado_nombre',
            width: '12%',
        },
        {
            title: 'Grupo Descanso',
            data: 'grupo_nombre',
            width: '12%',
            render: (data) => {
                return data || 'Sin grupo';
            }
        },
        {
            title: 'Estado',
            data: 'activo',
            width: '8%',
            render: (data) => {
                return data == 1
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';
            }
        },
        {
            title: 'Encargado',
            data: 'es_encargado',
            width: '8%',
            render: (data) => {
                return data == 1
                    ? '<i class="bi bi-star-fill text-warning"></i>'
                    : '';
            }
        },
        {
            title: 'Acciones',
            width: '13%',
            data: 'id_personal',
            searchable: false,
            orderable: false,
            render: (data, type, row) => {
                return `
        <button class='btn btn-acciones btn-modificar modificar' 
            title='Modificar personal'
            data-id_personal="${data}" 
            data-nombres="${row.nombres}"
            data-apellidos="${row.apellidos}"
            data-tipo="${row.tipo}"
            data-id_grado="${row.id_grado}"
            data-id_grupo_descanso="${row.id_grupo_descanso || ''}"
            data-fecha_ingreso="${row.fecha_ingreso}"
            data-activo="${row.activo}"
            data-es_encargado="${row.es_encargado}"
            data-observaciones="${row.observaciones || ''}">
            <i class='bi bi-pencil-square'></i> 
        </button>
        <button class='btn btn-acciones btn-eliminar eliminar' 
            title='Eliminar personal'
            data-id_personal="${data}">
            <i class='bi bi-trash'></i> 
        </button>
    `;
            }
        }

    ]
});

// Ocultar botones de modificar y cancelar al inicio
btnModificar.parentElement.style.display = 'none';
btnModificar.disabled = true;
btnCancelar.parentElement.style.display = 'none';
btnCancelar.disabled = true;

// Mostrar/ocultar formulario con el botón flotante
btnFlotante.addEventListener('click', () => {
    if (contenedorFormulario.style.display === 'none') {
        mostrarFormulario();
    } else {
        ocultarFormulario();
    }
});

const mostrarFormulario = () => {
    contenedorFormulario.style.display = '';
    contenedorFormulario.classList.add('slide-down');
    contenedorTabla.style.display = 'none';
    tituloFormulario.textContent = 'Nuevo Personal';
    formulario.reset();

    // ⭐ ESTABLECER valores por defecto
    radioActivoSi.checked = true;
    radioEncargadoNo.checked = true;

    // ⭐ DESHABILITAR grado y grupo hasta que se seleccione tipo
    selectGrado.disabled = true;
    selectGrupoDescanso.disabled = true;

    btnGuardar.parentElement.style.display = '';
    btnGuardar.disabled = false;
    btnModificar.parentElement.style.display = 'none';
    btnModificar.disabled = true;

    btnFlotante.classList.add('activo');
    btnFlotante.innerHTML = '<i class="bi bi-skip-backward"></i>';
    btnFlotante.setAttribute('title', 'Volver a la tabla');
};


const ocultarFormulario = () => {
    contenedorFormulario.classList.remove('slide-down');
    contenedorFormulario.classList.add('slide-up');
    setTimeout(() => {
        contenedorFormulario.style.display = 'none';
        contenedorFormulario.classList.remove('slide-up');
        contenedorTabla.style.display = '';
    }, 300);

    // Restaurar el botón flotante a modo "agregar"
    btnFlotante.classList.remove('activo');
    btnFlotante.innerHTML = '<i class="bi bi-plus"></i>';
    btnFlotante.setAttribute('title', 'Nuevo Personal');
};

const guardar = async (e) => {
    e.preventDefault();
    btnGuardar.disabled = true;

    if (!validarFormulario(formulario, ['id_personal'])) {
        Swal.fire({
            title: "Campos vacíos",
            text: "Debe llenar todos los campos obligatorios",
            icon: "info"
        });
        btnGuardar.disabled = false;
        return;
    }

    try {
        const body = new FormData(formulario);
        const url = "/TERCERA_CIA/API/personal/guardar"; // ⭐ CORREGIDO
        const config = {
            method: 'POST',
            body
        };

        const respuesta = await fetch(url, config);
        const data = await respuesta.json();
        const { codigo, mensaje, detalle } = data;

        let icon = 'info';
        if (codigo == 1) {
            icon = 'success';
            formulario.reset();
            buscar();
            ocultarFormulario();
        } else {
            icon = 'error';
            console.log(detalle);
        }

        Toast.fire({
            icon: icon,
            title: mensaje
        });

    } catch (error) {
        console.log(error);
        Toast.fire({
            icon: 'error',
            title: 'Error al guardar el personal'
        });
    }
    btnGuardar.disabled = false;
};

const buscar = async () => {
    try {
        const url = "/TERCERA_CIA/API/personal/buscar"; // ⭐ CORREGIDO
        const config = {
            method: 'GET'
        };

        console.log('🔍 URL completa:', window.location.origin + url);
        console.log('🔍 Buscando personal...');

        const respuesta = await fetch(url, config);
        console.log('🔍 Status HTTP:', respuesta.status);
        console.log('🔍 Headers:', respuesta.headers.get('content-type'));

        // ⭐ PRIMERO OBTENER EL TEXTO PARA VER QUÉ LLEGA
        const textoRespuesta = await respuesta.text();
        console.log('🔍 RESPUESTA COMPLETA (texto):', textoRespuesta);

        // Intentar parsear como JSON
        const data = JSON.parse(textoRespuesta);
        console.log('✅ Datos parseados:', data);

        const { datos } = data;
        console.log('✅ Array de personal:', datos);

        datatable.clear().draw();

        if (datos && datos.length > 0) {
            console.log('✅ Agregando ' + datos.length + ' registros a la tabla');
            datatable.rows.add(datos).draw();
        } else {
            console.log('⚠️ No hay datos para mostrar');
        }
    } catch (error) {
        console.error('❌ Error completo:', error);
        console.error('❌ Tipo de error:', error.name);
        console.error('❌ Mensaje:', error.message);
    }
};

const traerDatos = (e) => {
    const elemento = e.currentTarget.dataset;

    formulario.id_personal.value = elemento.id_personal;
    formulario.nombres.value = elemento.nombres;
    formulario.apellidos.value = elemento.apellidos;
    formulario.tipo.value = elemento.tipo;
    formulario.fecha_ingreso.value = elemento.fecha_ingreso;
    formulario.observaciones.value = elemento.observaciones;

    // ⭐ HABILITAR los selects para edición
    selectGrado.disabled = false;
    selectGrupoDescanso.disabled = false;

    // ⭐ Filtrar según el tipo antes de asignar valores
    filtrarGrados(elemento.tipo);
    filtrarGruposDescanso(elemento.tipo);

    // Ahora asignar los valores después de filtrar
    formulario.id_grado.value = elemento.id_grado;
    formulario.id_grupo_descanso.value = elemento.id_grupo_descanso || '';

    // ⭐ MARCAR EL RADIO BUTTON CORRECTO para activo
    if (elemento.activo == '1') {
        radioActivoSi.checked = true;
    } else {
        radioActivoNo.checked = true;
    }

    // ⭐ MARCAR EL RADIO BUTTON CORRECTO para encargado
    if (elemento.es_encargado == '1') {
        radioEncargadoSi.checked = true;
    } else {
        radioEncargadoNo.checked = true;
    }

    // Mostrar formulario y cambiar título
    contenedorFormulario.style.display = '';
    contenedorFormulario.classList.add('slide-down');
    contenedorTabla.style.display = 'none';
    tituloFormulario.textContent = 'Modificar Personal';

    // Cambiar botones
    btnGuardar.parentElement.style.display = 'none';
    btnGuardar.disabled = true;
    btnModificar.parentElement.style.display = '';
    btnModificar.disabled = false;
    btnCancelar.parentElement.style.display = '';
    btnCancelar.disabled = false;

    // Cambiar el botón flotante a modo "cerrar"
    btnFlotante.classList.add('activo');
    btnFlotante.innerHTML = '<i class="bi bi-x"></i>';
    btnFlotante.setAttribute('title', 'Cerrar formulario');
};

const cancelar = () => {
    ocultarFormulario();
    formulario.reset();
    btnGuardar.parentElement.style.display = '';
    btnGuardar.disabled = false;
    btnModificar.parentElement.style.display = 'none';
    btnModificar.disabled = true;
    btnCancelar.parentElement.style.display = 'none';
    btnCancelar.disabled = true;
};

const modificar = async (e) => {
    e.preventDefault();

    if (!validarFormulario(formulario)) {
        Swal.fire({
            title: "Campos vacíos",
            text: "Debe llenar todos los campos",
            icon: "info"
        });
        return;
    }

    try {
        const body = new FormData(formulario);
        const url = "/TERCERA_CIA/API/personal/modificar"; // ⭐ CORREGIDO
        const config = {
            method: 'POST',
            body
        };

        const respuesta = await fetch(url, config);
        const data = await respuesta.json();
        const { codigo, mensaje, detalle } = data;

        let icon = 'info';
        if (codigo == 1) {
            icon = 'success';
            formulario.reset();
            buscar();
            cancelar();
        } else {
            icon = 'error';
            console.log(detalle);
        }

        Toast.fire({
            icon: icon,
            title: mensaje
        });

    } catch (error) {
        console.log(error);
    }
};

const eliminar = async (e) => {
    const id_personal = e.currentTarget.dataset.id_personal;

    let confirmacion = await Swal.fire({
        icon: 'question',
        title: 'Confirmación',
        text: '¿Está seguro que desea eliminar este registro?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'No, cancelar',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    });

    if (confirmacion.isConfirmed) {
        try {
            const body = new FormData();
            body.append('id_personal', id_personal);
            const url = "/TERCERA_CIA/API/personal/eliminar"; // ⭐ CORREGIDO
            const config = {
                method: 'POST',
                body
            };

            const respuesta = await fetch(url, config);
            const data = await respuesta.json();
            const { codigo, mensaje, detalle } = data;

            let icon = 'info';
            if (codigo == 1) {
                icon = 'success';
                formulario.reset();
                buscar();
            } else {
                icon = 'error';
                console.log(detalle);
            }

            Toast.fire({
                icon: icon,
                title: mensaje
            });
        } catch (error) {
            console.log(error);
        }
    }
};

// Event listeners
formulario.addEventListener('submit', guardar);
btnCancelar.addEventListener('click', cancelar);
btnModificar.addEventListener('click', modificar);
datatable.on('click', '.modificar', traerDatos);
datatable.on('click', '.eliminar', eliminar);


// Referencias a los elementos de radio buttons
const radioActivoSi = document.getElementById('activo_si');
const radioActivoNo = document.getElementById('activo_no');
const radioEncargadoSi = document.getElementById('encargado_si');
const radioEncargadoNo = document.getElementById('encargado_no');

// ⭐ NUEVAS REFERENCIAS para filtrado dinámico
const selectTipo = document.getElementById('tipo');
const selectGrado = document.getElementById('id_grado');
const selectGrupoDescanso = document.getElementById('id_grupo_descanso');

// ⭐ Guardar todas las opciones originales al cargar
let opcionesGradosOriginales = [];
let opcionesGruposOriginales = [];

// ⭐ Función para guardar opciones originales
const guardarOpcionesOriginales = () => {
    // Guardar opciones de grados (excepto la primera que es "Seleccione...")
    opcionesGradosOriginales = Array.from(selectGrado.options).slice(1).map(option => ({
        value: option.value,
        text: option.text,
        tipo: option.dataset.tipo || option.getAttribute('data-tipo')
    }));

    // Guardar opciones de grupos (excepto la primera que es "Sin grupo asignado")
    opcionesGruposOriginales = Array.from(selectGrupoDescanso.options).slice(1).map(option => ({
        value: option.value,
        text: option.text,
        tipo: option.dataset.tipo || option.getAttribute('data-tipo')
    }));
};

// ⭐ Función para filtrar grados según tipo de personal
const filtrarGrados = (tipoPersonal) => {
    // Limpiar select (dejar solo la opción por defecto)
    selectGrado.innerHTML = '<option value="">Seleccione...</option>';

    if (!tipoPersonal) {
        // Si no hay tipo seleccionado, mostrar todos los grados
        opcionesGradosOriginales.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion.value;
            option.text = opcion.text;
            option.dataset.tipo = opcion.tipo;
            selectGrado.appendChild(option);
        });
        return;
    }

    // Filtrar y agregar solo los grados del tipo seleccionado
    const gradosFiltrados = opcionesGradosOriginales.filter(opcion => opcion.tipo === tipoPersonal);

    gradosFiltrados.forEach(opcion => {
        const option = document.createElement('option');
        option.value = opcion.value;
        option.text = opcion.text;
        option.dataset.tipo = opcion.tipo;
        selectGrado.appendChild(option);
    });
};

// ⭐ Función para filtrar grupos de descanso según tipo de personal
const filtrarGruposDescanso = (tipoPersonal) => {
    // Limpiar select (dejar solo la opción por defecto)
    selectGrupoDescanso.innerHTML = '<option value="">Sin grupo asignado</option>';

    if (!tipoPersonal) {
        // Si no hay tipo seleccionado, mostrar todos los grupos
        opcionesGruposOriginales.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion.value;
            option.text = opcion.text;
            option.dataset.tipo = opcion.tipo;
            selectGrupoDescanso.appendChild(option);
        });
        return;
    }

    // Filtrar y agregar solo los grupos del tipo seleccionado
    const gruposFiltrados = opcionesGruposOriginales.filter(opcion => opcion.tipo === tipoPersonal);

    gruposFiltrados.forEach(opcion => {
        const option = document.createElement('option');
        option.value = opcion.value;
        option.text = opcion.text;
        option.dataset.tipo = opcion.tipo;
        selectGrupoDescanso.appendChild(option);
    });
};

// ⭐ Event listener para el cambio de tipo de personal
selectTipo.addEventListener('change', (e) => {
    const tipoSeleccionado = e.target.value;

    if (tipoSeleccionado) {
        // Si hay tipo seleccionado, habilitar los selects
        selectGrado.disabled = false;
        selectGrupoDescanso.disabled = false;

        // Filtrar ambos selects
        filtrarGrados(tipoSeleccionado);
        filtrarGruposDescanso(tipoSeleccionado);
    } else {
        // Si no hay tipo, deshabilitar y limpiar
        selectGrado.disabled = true;
        selectGrupoDescanso.disabled = true;
        selectGrado.innerHTML = '<option value="">Seleccione...</option>';
        selectGrupoDescanso.innerHTML = '<option value="">Sin grupo asignado</option>';
    }

    // Resetear los valores seleccionados
    selectGrado.value = '';
    selectGrupoDescanso.value = '';
});

// Cargar datos al inicio
buscar();

// ⭐ Guardar opciones originales cuando cargue la página
window.addEventListener('DOMContentLoaded', () => {
    // Esperar un momento para que los selects se llenen desde PHP
    setTimeout(() => {
        guardarOpcionesOriginales();

        // ⭐ DESHABILITAR los selects al inicio
        selectGrado.disabled = true;
        selectGrupoDescanso.disabled = true;
    }, 100);
});