<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Merge de tres vías (diff3) por líneas, en PHP puro (sin depender de git ni de
 * binarios externos, para que funcione en cualquier hosting).
 *
 *   base   = versión original del core (la que se instaló)
 *   ours   = versión local (la que editó el proyecto)
 *   theirs = versión nueva del core
 *
 * Los cambios que no se pisan se combinan solos. Donde ambos lados cambiaron las
 * mismas líneas de forma distinta, se emiten marcadores de conflicto estilo git:
 *
 *   <<<<<<< proyecto
 *   (líneas locales)
 *   ||||||| base
 *   (líneas originales)
 *   =======
 *   (líneas del core nuevo)
 *   >>>>>>> core
 *
 * Usa el diff de Myers (O(ND)) para alinear base↔ours y base↔theirs y el
 * algoritmo clásico de diff3 sobre los "tramos estables" (líneas de base que
 * quedaron alineadas en ambos lados).
 */
final class ThreeWayMerge
{
    public const MARK_OURS   = '<<<<<<< proyecto';
    public const MARK_BASE   = '||||||| base';
    public const MARK_SEP    = '=======';
    public const MARK_THEIRS = '>>>>>>> core';

    /**
     * @return array{clean:bool,content:string,conflicts:int}
     */
    public static function merge(string $base, string $ours, string $theirs): array
    {
        // Atajos triviales.
        if ($ours === $theirs) {
            return ['clean' => true, 'content' => $ours, 'conflicts' => 0];
        }
        if ($ours === $base) {
            return ['clean' => true, 'content' => $theirs, 'conflicts' => 0];
        }
        if ($theirs === $base) {
            return ['clean' => true, 'content' => $ours, 'conflicts' => 0];
        }

        $o = self::lines($base);
        $a = self::lines($ours);
        $b = self::lines($theirs);

        $out = [];
        $conflicts = 0;
        foreach (self::chunks($o, $a, $b) as $c) {
            if ($c['stable']) {
                array_push($out, ...$c['o']);
                continue;
            }
            [$co, $ca, $cb] = [$c['o'], $c['a'], $c['b']];
            if ($ca === $cb) {
                array_push($out, ...$ca);           // ambos hicieron lo mismo
            } elseif ($ca === $co) {
                array_push($out, ...$cb);           // solo cambió el core
            } elseif ($cb === $co) {
                array_push($out, ...$ca);           // solo cambió el proyecto
            } else {
                $conflicts++;
                $out[] = self::MARK_OURS . "\n";
                array_push($out, ...self::terminated($ca));
                $out[] = self::MARK_BASE . "\n";
                array_push($out, ...self::terminated($co));
                $out[] = self::MARK_SEP . "\n";
                array_push($out, ...self::terminated($cb));
                $out[] = self::MARK_THEIRS . "\n";
            }
        }

        return ['clean' => $conflicts === 0, 'content' => implode('', $out), 'conflicts' => $conflicts];
    }

    /** ¿Parece binario? (byte nulo en los primeros 8 KB). No se mergean binarios. */
    public static function isBinary(string $content): bool
    {
        return str_contains(substr($content, 0, 8192), "\0");
    }

    /**
     * Divide en líneas conservando el terminador (\n o \r\n) pegado a cada línea,
     * así el resultado se reconstruye byte a byte con implode('').
     * @return array<int,string>
     */
    public static function lines(string $s): array
    {
        if ($s === '') {
            return [];
        }
        $parts = preg_split('/(?<=\n)/', $s, -1, PREG_SPLIT_NO_EMPTY);
        return $parts === false ? [$s] : $parts;
    }

    /**
     * Asegura que la última línea de un bloque termine en \n (para que el marcador
     * de conflicto que sigue quede en su propia línea).
     * @param array<int,string> $lines
     * @return array<int,string>
     */
    private static function terminated(array $lines): array
    {
        $n = count($lines);
        if ($n > 0 && !str_ends_with($lines[$n - 1], "\n")) {
            $lines[$n - 1] .= "\n";
        }
        return $lines;
    }

    /**
     * Recorre base/ours/theirs y los parte en tramos estables (iguales en los tres)
     * e inestables (al menos un lado difiere de base).
     *
     * @param array<int,string> $o
     * @param array<int,string> $a
     * @param array<int,string> $b
     * @return array<int,array{stable:bool,o:array<int,string>,a:array<int,string>,b:array<int,string>}>
     */
    public static function chunks(array $o, array $a, array $b): array
    {
        $ma = self::matches($o, $a); // índice en base => índice en ours
        $mb = self::matches($o, $b); // índice en base => índice en theirs

        $no = count($o);
        $na = count($a);
        $nb = count($b);
        $lo = $la = $lb = 0;
        $chunks = [];

        while (true) {
            // 1) Tramo estable: base, ours y theirs avanzan juntos.
            $i = $lo;
            while ($i < $no
                && ($ma[$i] ?? -1) === $la + ($i - $lo)
                && ($mb[$i] ?? -1) === $lb + ($i - $lo)) {
                $i++;
            }
            if ($i > $lo) {
                $len = $i - $lo;
                $chunks[] = ['stable' => true, 'o' => array_slice($o, $lo, $len), 'a' => [], 'b' => []];
                $lo = $i;
                $la += $len;
                $lb += $len;
                continue;
            }

            // 2) Buscar la próxima línea de base alineada en ambos lados.
            $next = null;
            for ($j = $lo; $j < $no; $j++) {
                if (isset($ma[$j], $mb[$j])) {
                    $next = $j;
                    break;
                }
            }

            if ($next === null) {
                // Resto inestable hasta el final.
                if ($lo < $no || $la < $na || $lb < $nb) {
                    $chunks[] = [
                        'stable' => false,
                        'o' => array_slice($o, $lo),
                        'a' => array_slice($a, $la),
                        'b' => array_slice($b, $lb),
                    ];
                }
                break;
            }

            $chunks[] = [
                'stable' => false,
                'o' => array_slice($o, $lo, $next - $lo),
                'a' => array_slice($a, $la, $ma[$next] - $la),
                'b' => array_slice($b, $lb, $mb[$next] - $lb),
            ];
            $lo = $next;
            $la = $ma[$next];
            $lb = $mb[$next];
        }

        return $chunks;
    }

    /**
     * Líneas comunes entre $x e $y (LCS vía Myers), como mapa índiceX => índiceY.
     * Recorta prefijo/sufijo común antes de correr Myers (el caso típico de un
     * archivo con pocas ediciones queda casi gratis).
     *
     * @param array<int,string> $x
     * @param array<int,string> $y
     * @return array<int,int>
     */
    public static function matches(array $x, array $y): array
    {
        $n = count($x);
        $m = count($y);
        $map = [];

        // Prefijo común.
        $p = 0;
        while ($p < $n && $p < $m && $x[$p] === $y[$p]) {
            $map[$p] = $p;
            $p++;
        }
        // Sufijo común.
        $s = 0;
        while ($s < $n - $p && $s < $m - $p && $x[$n - 1 - $s] === $y[$m - 1 - $s]) {
            $s++;
        }

        $mx = array_slice($x, $p, $n - $p - $s);
        $my = array_slice($y, $p, $m - $p - $s);
        foreach (self::myers($mx, $my) as $i => $j) {
            $map[$i + $p] = $j + $p;
        }
        for ($k = 0; $k < $s; $k++) {
            $map[$n - $s + $k] = $m - $s + $k;
        }

        ksort($map);
        return $map;
    }

    /**
     * Diff de Myers: devuelve los pares alineados (diagonales) del camino de edición
     * más corto, como mapa índiceX => índiceY.
     *
     * @param array<int,string> $x
     * @param array<int,string> $y
     * @return array<int,int>
     */
    private static function myers(array $x, array $y): array
    {
        $n = count($x);
        $m = count($y);
        if ($n === 0 || $m === 0) {
            return [];
        }

        $max = $n + $m;
        $v = [1 => 0];
        $trace = [];
        $found = false;

        for ($d = 0; $d <= $max; $d++) {
            $trace[] = $v;
            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || ($k !== $d && ($v[$k - 1] ?? -1) < ($v[$k + 1] ?? -1))) {
                    $px = $v[$k + 1] ?? 0;          // baja (inserción en y)
                } else {
                    $px = ($v[$k - 1] ?? 0) + 1;    // derecha (borrado de x)
                }
                $py = $px - $k;
                while ($px < $n && $py < $m && $x[$px] === $y[$py]) {
                    $px++;
                    $py++;
                }
                $v[$k] = $px;
                if ($px >= $n && $py >= $m) {
                    $found = true;
                    break 2;
                }
            }
        }

        if (!$found) {
            return [];
        }

        // Backtracking por el trace para recuperar las diagonales.
        $map = [];
        $cx = $n;
        $cy = $m;
        for ($d = count($trace) - 1; $d >= 0; $d--) {
            $vd = $trace[$d];
            $k = $cx - $cy;
            if ($k === -$d || ($k !== $d && ($vd[$k - 1] ?? -1) < ($vd[$k + 1] ?? -1))) {
                $prevK = $k + 1;
            } else {
                $prevK = $k - 1;
            }
            $prevX = $vd[$prevK] ?? 0;
            $prevY = $prevX - $prevK;

            while ($cx > $prevX && $cy > $prevY) {
                $cx--;
                $cy--;
                $map[$cx] = $cy;
            }
            if ($d > 0) {
                $cx = $prevX;
                $cy = $prevY;
            }
        }

        return $map;
    }
}
