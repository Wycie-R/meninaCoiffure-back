<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menina Coiffure — Balanceo de carga</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=gloock:400|instrument-sans:400,500,600&display=swap" rel="stylesheet">
    <style>
        /* Misma paleta que resources/css/app.css; serie A y serie B son los colores de cada nodo */
        :root {
            --noche: #17111c;
            --velo: #1f1825;
            --terciopelo: #282030;
            --hilo: #3d3247;
            --perla: #f6eff4;
            --bruma: #bcaec1;
            --niebla: #9e91a4;
            --menta: #8fd5b5;
            --borgona: #f08ca5;
            --nodo-a: #a873c9;
            --nodo-b: #c98500;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background-color: var(--noche);
            color: var(--perla);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }

        .contenedor {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        h1 {
            font-family: 'Gloock', Georgia, serif;
            font-weight: 400;
            font-size: 2.2rem;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--niebla);
            margin: 0 0 12px;
        }

        .subtitulo { color: var(--bruma); margin: 0; }

        a.boton, button {
            font: inherit;
            font-weight: 600;
            border: 1px solid var(--hilo);
            background-color: var(--terciopelo);
            color: var(--perla);
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
        }

        a.boton:hover, button:hover { border-color: var(--bruma); }
        button.activo { background-color: var(--menta); color: var(--noche); border-color: var(--menta); }

        .panel {
            background-color: var(--velo);
            border: 1px solid var(--hilo);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .aviso {
            display: none;
            border-color: var(--borgona);
            color: var(--borgona);
        }

        .nodos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .nodo { border-top: 6px solid var(--color-nodo); }
        .nodo .encabezado { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .nodo .nombre { font-size: 1.3rem; font-weight: 600; color: var(--color-nodo); }
        .nodo .cuenta { font-family: 'Gloock', Georgia, serif; font-size: 3.5rem; line-height: 1.1; margin: 12px 0 4px; }
        .nodo .detalle { color: var(--bruma); font-size: 0.9rem; margin: 4px 0 0; }

        .barra { height: 10px; background-color: var(--terciopelo); border-radius: 999px; overflow: hidden; margin-top: 12px; }
        .barra div { height: 100%; width: 0; background-color: var(--color-nodo); transition: width 0.3s; }

        .estado {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            background-color: var(--terciopelo);
            color: var(--niebla);
            white-space: nowrap;
        }
        .estado.atendiendo { background-color: var(--menta); color: var(--noche); }
        .estado.caido { background-color: var(--borgona); color: var(--noche); }

        .tira { display: flex; flex-wrap: wrap; gap: 4px; min-height: 18px; }
        .tira span { width: 14px; height: 14px; border-radius: 4px; }

        .leyenda { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 12px; color: var(--bruma); font-size: 0.85rem; }
        .leyenda i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; }

        .controles { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .control { display: flex; flex-direction: column; gap: 4px; min-width: 220px; flex: 1; }
        .control label { color: var(--bruma); font-size: 0.9rem; }
        input[type=range] { width: 100%; accent-color: var(--nodo-a); }

        .metricas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .metrica .valor { font-family: 'Gloock', Georgia, serif; font-size: 1.8rem; }
        .metrica .etiqueta { color: var(--niebla); font-size: 0.85rem; }

        .tabla-envoltura { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--hilo); white-space: nowrap; }
        th { color: var(--niebla); font-weight: 500; }

        code { background-color: var(--terciopelo); padding: 2px 6px; border-radius: 6px; }
        ol { margin: 0; padding-left: 20px; color: var(--bruma); line-height: 1.8; }
    </style>
</head>
<body>
<div class="contenedor">
    <header>
        <div>
            <h1>Balanceo de carga</h1>
            <p class="subtitulo">HAProxy reparte cada petición entre el nodo A y el nodo B con <strong>leastconn</strong>: va al que tiene menos trabajo en curso.</p>
        </div>
        <a class="boton" id="enlace-stats" href="#" target="_blank" rel="noopener">Panel de HAProxy (8404)</a>
    </header>

    <div class="panel aviso" id="aviso-sin-haproxy">
        Las respuestas no traen la cabecera <code>X-Servido-Por</code>: esta página no se abrió a través de HAProxy.
        Entrá por <code>http://localhost:8000/balanceo</code>.
    </div>

    <section class="nodos">
        <article class="panel nodo" style="--color-nodo: var(--nodo-a)">
            <div class="encabezado">
                <span class="nombre">Nodo A</span>
                <span class="estado" id="estado-nodo_a">Esperando tráfico</span>
            </div>
            <div class="cuenta" id="cuenta-nodo_a">0</div>
            <p class="detalle"><span id="porcentaje-nodo_a">0 %</span> de las peticiones · nginx → app</p>
            <p class="detalle" id="php-nodo_a">PHP-FPM: —</p>
            <div class="barra"><div id="barra-nodo_a"></div></div>
        </article>

        <article class="panel nodo" style="--color-nodo: var(--nodo-b)">
            <div class="encabezado">
                <span class="nombre">Nodo B</span>
                <span class="estado" id="estado-nodo_b">Esperando tráfico</span>
            </div>
            <div class="cuenta" id="cuenta-nodo_b">0</div>
            <p class="detalle"><span id="porcentaje-nodo_b">0 %</span> de las peticiones · nginx_b → app_b</p>
            <p class="detalle" id="php-nodo_b">PHP-FPM: —</p>
            <div class="barra"><div id="barra-nodo_b"></div></div>
        </article>
    </section>

    <section class="panel">
        <h2>Últimas 60 peticiones</h2>
        <div class="tira" id="tira"></div>
        <div class="leyenda">
            <span><i style="background-color: var(--nodo-a)"></i>Nodo A</span>
            <span><i style="background-color: var(--nodo-b)"></i>Nodo B</span>
            <span><i style="background-color: var(--borgona)"></i>Error (ningún nodo disponible)</span>
        </div>
    </section>

    <section class="panel">
        <h2>Generar tráfico</h2>
        <div class="controles">
            <button type="button" id="boton-rafaga">Ráfaga de 20 peticiones</button>
            <button type="button" id="boton-continuo">Iniciar tráfico continuo</button>
            <button type="button" id="boton-reiniciar">Reiniciar contadores</button>
        </div>
        <div class="controles" style="margin-top: 16px">
            <div class="control">
                <label for="rango-espera">Trabajo simulado por petición: <strong id="texto-espera">300 ms</strong></label>
                <input type="range" id="rango-espera" min="0" max="1500" step="100" value="300">
            </div>
            <div class="control">
                <label for="rango-ritmo">Tráfico continuo: <strong id="texto-ritmo">5 peticiones/s</strong></label>
                <input type="range" id="rango-ritmo" min="1" max="20" step="1" value="5">
            </div>
        </div>
    </section>

    <section class="panel metricas">
        <div class="metrica"><div class="valor" id="metrica-total">0</div><div class="etiqueta">Peticiones</div></div>
        <div class="metrica"><div class="valor" id="metrica-en-vuelo">0</div><div class="etiqueta">En curso ahora</div></div>
        <div class="metrica"><div class="valor" id="metrica-latencia">—</div><div class="etiqueta">Latencia promedio</div></div>
        <div class="metrica"><div class="valor" id="metrica-errores">0</div><div class="etiqueta">Errores</div></div>
    </section>

    <section class="panel">
        <h2>Registro</h2>
        <div class="tabla-envoltura">
            <table>
                <thead>
                <tr><th>Hora</th><th>HAProxy eligió</th><th>PHP que respondió</th><th>PID</th><th>Latencia</th><th>Estado</th></tr>
                </thead>
                <tbody id="registro"></tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Demostración de failover</h2>
        <ol>
            <li>Iniciá el tráfico continuo: los dos nodos reciben peticiones.</li>
            <li>Apagá un nodo: <code>docker compose stop nginx_b</code>. En unos 2 s queda "Sin respuesta" y todo el tráfico pasa al nodo A sin errores.</li>
            <li>Volvé a encenderlo: <code>docker compose start nginx_b</code>. HAProxy lo detecta y vuelve a repartir.</li>
            <li>También se puede poner un nodo en mantenimiento desde el panel de HAProxy (usuario <code>admin</code>, contraseña <code>admin</code>).</li>
        </ol>
    </section>
</div>

<script>
    // Nombres de servidor tal como están en haproxy.cfg (los devuelve la cabecera X-Servido-Por)
    const NODOS = ['nodo_a', 'nodo_b'];
    const COLORES = { nodo_a: 'var(--nodo-a)', nodo_b: 'var(--nodo-b)', error: 'var(--borgona)' };
    const NOMBRES = { nodo_a: 'Nodo A', nodo_b: 'Nodo B', error: 'Error' };
    const LARGO_TIRA = 60;
    const LARGO_REGISTRO = 15;
    // Si un nodo no responde en este tiempo mientras hay tráfico, se considera fuera del balanceo
    const SEGUNDOS_SIN_RESPUESTA = 3;

    const conteo = { nodo_a: 0, nodo_b: 0 };
    const ultimaRespuesta = { nodo_a: 0, nodo_b: 0 };
    let total = 0;
    let errores = 0;
    let sumaLatencias = 0;
    let respuestasOk = 0;
    let enVuelo = 0;
    let ultimoEnvio = 0;
    let temporizadorContinuo = null;
    const historial = [];

    const rangoEspera = document.getElementById('rango-espera');
    const rangoRitmo = document.getElementById('rango-ritmo');
    const botonContinuo = document.getElementById('boton-continuo');

    document.getElementById('enlace-stats').href = 'http://' + window.location.hostname + ':8404/stats';

    async function enviarPeticion() {
        const espera = rangoEspera.value;
        const inicio = performance.now();
        enVuelo = enVuelo + 1;
        ultimoEnvio = Date.now();

        let servidor = 'error';
        let datos = null;
        let estadoHttp = 0;

        try {
            const respuesta = await fetch('/api/balanceo/ping?espera=' + espera, {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            estadoHttp = respuesta.status;

            if (respuesta.ok) {
                datos = await respuesta.json();
                const cabecera = respuesta.headers.get('X-Servido-Por');

                if (cabecera === null) {
                    // Sin cabecera la petición no pasó por HAProxy; se usa el nombre del contenedor PHP
                    document.getElementById('aviso-sin-haproxy').style.display = 'block';
                    servidor = datos.nodo.replace('-', '_');
                } else {
                    servidor = cabecera;
                }
            }
        } catch (error) {
            // La red falló por completo (por ejemplo, HAProxy apagado)
            estadoHttp = 0;
        }

        const latencia = Math.round(performance.now() - inicio);
        enVuelo = enVuelo - 1;
        registrarResultado(servidor, datos, estadoHttp, latencia);
    }

    function registrarResultado(servidor, datos, estadoHttp, latencia) {
        total = total + 1;

        if (servidor in conteo) {
            conteo[servidor] = conteo[servidor] + 1;
            ultimaRespuesta[servidor] = Date.now();
            sumaLatencias = sumaLatencias + latencia;
            respuestasOk = respuestasOk + 1;
            document.getElementById('php-' + servidor).textContent = 'PHP-FPM: ' + datos.nodo + ' · PID ' + datos.pid;
        } else {
            servidor = 'error';
            errores = errores + 1;
        }

        historial.push(servidor);
        if (historial.length > LARGO_TIRA) {
            historial.shift();
        }

        agregarFilaAlRegistro(servidor, datos, estadoHttp, latencia);
        dibujar();
    }

    function agregarFilaAlRegistro(servidor, datos, estadoHttp, latencia) {
        const fila = document.createElement('tr');
        let textoEstado = 'HTTP ' + estadoHttp;
        if (estadoHttp === 0) {
            textoEstado = 'Sin conexión';
        }

        const celdas = [
            new Date().toLocaleTimeString(),
            NOMBRES[servidor],
            datos ? datos.nodo : '—',
            datos ? datos.pid : '—',
            latencia + ' ms',
            textoEstado,
        ];

        for (const texto of celdas) {
            const celda = document.createElement('td');
            celda.textContent = texto;
            fila.appendChild(celda);
        }
        fila.children[1].style.color = COLORES[servidor];

        const registro = document.getElementById('registro');
        registro.prepend(fila);
        while (registro.children.length > LARGO_REGISTRO) {
            registro.lastChild.remove();
        }
    }

    function dibujar() {
        for (const nodo of NODOS) {
            let porcentaje = 0;
            if (respuestasOk > 0) {
                porcentaje = Math.round((conteo[nodo] / respuestasOk) * 100);
            }
            document.getElementById('cuenta-' + nodo).textContent = conteo[nodo];
            document.getElementById('porcentaje-' + nodo).textContent = porcentaje + ' %';
            document.getElementById('barra-' + nodo).style.width = porcentaje + '%';
        }

        const tira = document.getElementById('tira');
        tira.innerHTML = '';
        for (const servidor of historial) {
            const cuadro = document.createElement('span');
            cuadro.style.backgroundColor = COLORES[servidor];
            cuadro.title = NOMBRES[servidor];
            tira.appendChild(cuadro);
        }

        document.getElementById('metrica-total').textContent = total;
        document.getElementById('metrica-en-vuelo').textContent = enVuelo;
        document.getElementById('metrica-errores').textContent = errores;
        if (respuestasOk > 0) {
            document.getElementById('metrica-latencia').textContent = Math.round(sumaLatencias / respuestasOk) + ' ms';
        } else {
            document.getElementById('metrica-latencia').textContent = '—';
        }
    }

    // Se recalcula aparte porque un nodo caído deja de responder y no dispara ningún evento
    function actualizarEstados() {
        const ahora = Date.now();
        const hayTrafico = (ahora - ultimoEnvio) / 1000 < SEGUNDOS_SIN_RESPUESTA;

        for (const nodo of NODOS) {
            const etiqueta = document.getElementById('estado-' + nodo);
            const segundosDesdeRespuesta = (ahora - ultimaRespuesta[nodo]) / 1000;

            if (ultimaRespuesta[nodo] === 0 && !hayTrafico) {
                etiqueta.textContent = 'Esperando tráfico';
                etiqueta.className = 'estado';
            } else if (segundosDesdeRespuesta <= SEGUNDOS_SIN_RESPUESTA) {
                etiqueta.textContent = 'Atendiendo';
                etiqueta.className = 'estado atendiendo';
            } else if (hayTrafico) {
                etiqueta.textContent = 'Sin respuesta';
                etiqueta.className = 'estado caido';
            } else {
                etiqueta.textContent = 'En espera';
                etiqueta.className = 'estado';
            }
        }
        document.getElementById('metrica-en-vuelo').textContent = enVuelo;
    }

    function iniciarTraficoContinuo() {
        const milisegundosEntreEnvios = 1000 / Number(rangoRitmo.value);
        temporizadorContinuo = setInterval(enviarPeticion, milisegundosEntreEnvios);
        botonContinuo.textContent = 'Detener tráfico continuo';
        botonContinuo.classList.add('activo');
    }

    function detenerTraficoContinuo() {
        clearInterval(temporizadorContinuo);
        temporizadorContinuo = null;
        botonContinuo.textContent = 'Iniciar tráfico continuo';
        botonContinuo.classList.remove('activo');
    }

    document.getElementById('boton-rafaga').addEventListener('click', function () {
        // Las 20 salen a la vez: así se ve cómo leastconn las reparte entre los dos nodos
        for (let i = 0; i < 20; i++) {
            enviarPeticion();
        }
    });

    botonContinuo.addEventListener('click', function () {
        if (temporizadorContinuo === null) {
            iniciarTraficoContinuo();
        } else {
            detenerTraficoContinuo();
        }
    });

    document.getElementById('boton-reiniciar').addEventListener('click', function () {
        for (const nodo of NODOS) {
            conteo[nodo] = 0;
            ultimaRespuesta[nodo] = 0;
            document.getElementById('php-' + nodo).textContent = 'PHP-FPM: —';
        }
        total = 0;
        errores = 0;
        sumaLatencias = 0;
        respuestasOk = 0;
        historial.length = 0;
        document.getElementById('registro').innerHTML = '';
        dibujar();
    });

    rangoEspera.addEventListener('input', function () {
        document.getElementById('texto-espera').textContent = rangoEspera.value + ' ms';
    });

    rangoRitmo.addEventListener('input', function () {
        document.getElementById('texto-ritmo').textContent = rangoRitmo.value + ' peticiones/s';
        // Si el tráfico ya corre, se reinicia con el nuevo ritmo
        if (temporizadorContinuo !== null) {
            detenerTraficoContinuo();
            iniciarTraficoContinuo();
        }
    });

    setInterval(actualizarEstados, 250);
</script>
</body>
</html>
