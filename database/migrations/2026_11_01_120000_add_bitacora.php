<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('The clinic schema requires PostgreSQL.');
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.bitacora (
   id_bitacora INTEGER GENERATED ALWAYS AS IDENTITY NOT NULL,
   fecha_hora TIMESTAMPTZ NOT NULL,
   accion VARCHAR(40) NOT NULL,
   modulo VARCHAR(40) NOT NULL,
   detalle TEXT NOT NULL,
   direccion_ip VARCHAR(45),
   actor_nombre VARCHAR(100),
   actor_rol VARCHAR(100),
   id_usuario INTEGER,
   id_registro INTEGER,
   cambios TEXT,
   CONSTRAINT pk_bitacora PRIMARY KEY (id_bitacora),
   CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario)
       REFERENCES public.usuario (id_usuario),
   CONSTRAINT ck_bitacora_accion CHECK (
       accion IN (
           'Inicio de sesión',
           'Cierre de sesión',
           'Intento fallido',
           'Creación',
           'Actualización',
           'Reprogramación',
           'Cancelación',
           'Desactivación',
           'Activación',
           'Asistencia',
           'Atención'
       )
   )
);
CREATE INDEX IF NOT EXISTS ix_bitacora_fecha ON public.bitacora (fecha_hora);
CREATE INDEX IF NOT EXISTS ix_bitacora_usuario ON public.bitacora (id_usuario);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS public.bitacora');
    }
};
