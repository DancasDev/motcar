<?php

namespace App\Controllers\Traits;

/**
 * TimingAttack
 *
 * Garantiza que la respuesta a un endpoint se entregue siempre en un tiempo
 * constante, independientemente de si el proceso real tomó más o menos.
 * Así se evita que un atacante pueda inferir información (ej: existencia de
 * un usuario) midiendo la diferencia en los tiempos de respuesta.
 *
 * ─── Cómo usar ───
 *
 * 1. En el controller, importar el Trait:
 *    use App\Controllers\Traits\TimingAttack;
 *
 * 2. Usarlo dentro de la clase:
 *    class Foo extends BaseController {
 *        use TimingAttack;
 *
 *        public function bar(): Response {
 *            $this->startTimingAttack();     // Inicia el cronómetro
 *            // ... lógica del endpoint ...
 *            $this->endTimingAttack();       // Espera lo que falte
 *            return $this->respond([...]);
 *        }
 *    }
 *
 * @see \App\Controllers\V1\Services\Password\Recovery (ejemplo de uso)
 */
trait TimingAttack {
    private float $timingStart = 0;
    private int $timingTarget = 4500;
    private int $timingJitter = 200;

    /**
     * @param int $targetMs  Tiempo total deseado en milisegundos.
     *                       Default: 4500 (4.5s).
     * @param int $jitterMs  Variación aleatoria en ms para no ser detectable.
     *                       Default: 200 (±200ms).
     */
    public function startTimingAttack(int $targetMs = 4500, int $jitterMs = 200): void {
        $this->timingStart = microtime(true);
        $this->timingTarget = $targetMs;
        $this->timingJitter = $jitterMs;
    }

    /**
     * Detiene el cronómetro y duerme lo que falte para alcanzar
     * el target, agregando un jitter configurable.
     */
    public function endTimingAttack(): void {
        $elapsed = (microtime(true) - $this->timingStart) * 1000;
        $remaining = $this->timingTarget - $elapsed + rand(-$this->timingJitter, $this->timingJitter);
        if ($remaining > 0) {
            usleep((int) ($remaining * 1000));
        }
    }
}
