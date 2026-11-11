<?php

use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use App\Services\AvisoCorreo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Comprueba la configuración de correo (MAIL_* en el .env). Sin argumento usa AVISOS_CORREO_DESTINO
Artisan::command('correo:probar {destino?}', function (AvisoCorreo $aviso) {
    $destino = $this->argument('destino');
    if (blank($destino)) {
        $destino = config('services.avisos.correo_destino');
    }

    if (blank($destino)) {
        $this->error('Falta el destino: pásalo como argumento o completa AVISOS_CORREO_DESTINO en el .env.');
        return 1;
    }

    $enviado = $aviso->enviar(
        (string) $destino,
        'Prueba de Menina Coiffure',
        'Prueba de Menina Coiffure: los avisos de pedidos de cita llegan a este correo.'
    );

    if (!$enviado) {
        $this->error('El servidor de correo no aceptó el mensaje. Revisa MAIL_* en el .env (detalle en storage/logs/laravel.log).');
        return 1;
    }

    // Con MAIL_MAILER=log no sale nada a internet: se avisa para no buscar un correo que no llegará
    if (config('mail.default') === 'log') {
        $this->warn('MAIL_MAILER=log: el correo no se envió, quedó escrito en el log (storage/logs/laravel.log o docker compose logs).');
        return 0;
    }

    $this->info('Listo: revisa la bandeja de ' . $destino . ' (y la carpeta de spam).');

    return 0;
})->purpose('Envía un correo de prueba para comprobar la configuración de correo');

// Cuentas del equipo (administración y profesionales). No hay pantalla pública para crearlas: solo
// se crean desde la terminal del servidor, a la que se entra por SSH con llave
Artisan::command('salon:crear-cuenta {--rol= : admin o profesional} {--email= : correo para iniciar sesión} {--nombre= : nombre que se muestra en el panel} {--personal-email= : correo de su ficha de personal}', function () {
    $rol = $this->option('rol');
    if (blank($rol)) {
        $rol = $this->choice('Rol de la cuenta', ['admin', 'profesional'], 0);
    }

    $email = $this->option('email');
    if (blank($email)) {
        $email = $this->ask('Correo con el que va a iniciar sesión');
    }

    $nombre = $this->option('nombre');
    if (blank($nombre)) {
        $nombre = $this->ask('Nombre que se muestra en el panel');
    }

    // Una profesional sin ficha no ve ninguna agenda; la administración puede no tenerla todavía
    $emailFicha = $this->option('personal-email');
    if (blank($emailFicha) && $rol === 'profesional') {
        $emailFicha = $this->ask('Correo de su ficha de personal (la carga la administración desde el panel)');
    }

    // La contraseña nunca va como opción: quedaría guardada en el historial de la terminal
    $password = $this->secret('Contraseña (mínimo 12 caracteres)');
    $confirmacion = $this->secret('Repite la contraseña');

    $datos = [
        'rol' => $rol,
        'email' => strtolower(trim((string) $email)),
        'nombre' => trim((string) $nombre),
        'password' => (string) $password,
        'password_confirmation' => (string) $confirmacion,
    ];
    $validacion = Validator::make($datos, [
        'rol' => ['required', 'in:admin,profesional'],
        'email' => ['required', 'email', 'max:255'],
        'nombre' => ['required', 'string', 'max:255'],
        'password' => ['required', 'string', 'min:12', 'confirmed'],
    ], [
        'rol.in' => 'El rol tiene que ser admin o profesional.',
        'email.required' => 'Falta el correo.',
        'email.email' => 'Ese correo no es válido.',
        'nombre.required' => 'Falta el nombre.',
        'password.required' => 'Falta la contraseña.',
        'password.min' => 'La contraseña tiene que tener al menos 12 caracteres.',
        'password.confirmed' => 'Las contraseñas no coinciden.',
    ]);

    if ($validacion->fails()) {
        foreach ($validacion->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }

    if (User::whereRaw('LOWER(email) = ?', [$datos['email']])->exists()) {
        $this->error('Ya hay una cuenta con el correo ' . $datos['email'] . '.');
        return 1;
    }

    $personalId = null;
    if (filled($emailFicha)) {
        $ficha = Personal::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $emailFicha))])->first();
        if ($ficha === null) {
            $this->error('No hay ninguna ficha de personal con el correo ' . $emailFicha . '. Cárgala primero desde el panel (Personal).');
            return 1;
        }
        if (User::where('personal_id', $ficha->id)->exists()) {
            $this->error('La ficha de ' . $ficha->nombre . ' ya está vinculada a otra cuenta.');
            return 1;
        }
        $personalId = $ficha->id;
    } elseif ($rol === 'profesional') {
        $this->error('Una profesional necesita su ficha de personal: sin ella no ve ninguna agenda.');
        return 1;
    }

    $usuario = User::create([
        'name' => $datos['nombre'],
        'email' => $datos['email'],
        // El cast 'hashed' del modelo la guarda hasheada
        'password' => $datos['password'],
        'rol_id' => Role::firstOrCreate(['nombre' => $rol])->id,
        'personal_id' => $personalId,
    ]);

    $this->info('Cuenta creada: ' . $usuario->email . ' (' . $rol . ').');
    if ($personalId === null) {
        $this->line('Sin ficha de personal. Para vincularla después: php artisan salon:vincular-ficha ' . $usuario->email . ' correo-de-la-ficha');
    }

    return 0;
})->purpose('Crea una cuenta de administración o de profesional (solo por terminal)');

// La dueña crea su propia ficha de personal desde el panel después de entrar por primera vez:
// este comando la vincula a su cuenta para que el sistema sepa que también atiende
Artisan::command('salon:vincular-ficha {email : correo de la cuenta} {personal-email : correo de la ficha de personal}', function () {
    $usuario = User::whereRaw('LOWER(email) = ?', [strtolower(trim($this->argument('email')))])->first();
    if ($usuario === null) {
        $this->error('No hay ninguna cuenta con el correo ' . $this->argument('email') . '.');
        return 1;
    }
    if ($usuario->hasRole('cliente')) {
        $this->error('Esa cuenta es de una clienta: solo se vinculan cuentas del equipo.');
        return 1;
    }

    $ficha = Personal::whereRaw('LOWER(email) = ?', [strtolower(trim($this->argument('personal-email')))])->first();
    if ($ficha === null) {
        $this->error('No hay ninguna ficha de personal con el correo ' . $this->argument('personal-email') . '.');
        return 1;
    }

    $otraCuenta = User::where('personal_id', $ficha->id)->where('id', '!=', $usuario->id)->exists();
    if ($otraCuenta) {
        $this->error('La ficha de ' . $ficha->nombre . ' ya está vinculada a otra cuenta.');
        return 1;
    }

    $usuario->personal_id = $ficha->id;
    $usuario->save();

    $this->info('Listo: ' . $usuario->email . ' quedó vinculada a la ficha de ' . $ficha->nombre . ' ' . $ficha->apellido . '.');

    return 0;
})->purpose('Vincula una cuenta del equipo con su ficha de personal');
