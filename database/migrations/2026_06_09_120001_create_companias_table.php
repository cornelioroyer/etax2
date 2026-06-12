<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ruc');
            $table->string('dv');
            $table->string('firma_cartas')->nullable();
            $table->text('direccion');
            $table->string('telefono_1')->nullable();
            $table->string('telefono_2')->nullable();
            $table->string('e_mail');
            $table->string('cargo')->nullable();
            $table->text('mensaje')->nullable();
            $table->integer('correlativo');
            $table->date('fecha_de_apertura');
            $table->date('fecha_de_expiracion');
            $table->string('status');
            $table->string('no_patronal')->nullable();
            $table->string('act_economica')->nullable();
            $table->string('cedula')->nullable();
            $table->string('licencia')->nullable();
            $table->string('repre_legal')->nullable();
            $table->foreignId('zonas_id')->constrained('zonas');
            $table->string('tipo_de_entidad')->nullable();
            $table->text('constitucion')->nullable();
            $table->string('logo')->nullable();
            $table->string('token')->nullable();
            $table->string('cliente')->nullable();
            $table->text('token_factura_fiscal')->nullable();
            $table->string('clave_factura_fiscal')->nullable();
            $table->text('url_factura_fiscal')->nullable();
            $table->string('nit')->nullable();
            $table->string('cedula_repre_legal')->nullable();
            $table->string('sello')->nullable();
            $table->string('municipio')->nullable();
            $table->string('clave_municipio')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index('token', 'idx_01_companias');
            $table->index('e_mail', 'idx_02_companias');
            $table->index('zonas_id', 'idx_03_companias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companias');
    }
};
