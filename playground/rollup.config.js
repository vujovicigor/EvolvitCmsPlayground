import { spawn } from 'node:child_process';
import svelte from 'rollup-plugin-svelte';
import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import terser from '@rollup/plugin-terser';
import css from 'rollup-plugin-css-only';
import livereload from 'rollup-plugin-livereload';

const production = !process.env.ROLLUP_WATCH;

function serve() {
	let started = false;
	return {
		writeBundle() {
			if (!started) {
				started = true;
				spawn('npm', ['run', 'start', '--', '--dev'], { stdio: ['ignore', 'inherit', 'inherit'], shell: true });
			}
		}
	};
}

export default {
	input: 'src/main.js',
	output: {
		sourcemap: true,
		format: 'iife',
		name: 'app',
		file: 'public/build/bundle.js'
	},
	plugins: [
		svelte({ compilerOptions: { dev: !production } }),
		css({ output: 'bundle.css' }),
		resolve({ browser: true, dedupe: ['svelte'], exportConditions: ['svelte'] }),
		commonjs(),
		!production && serve(),
		!production && livereload('public'),
		production && terser()
	],
	watch: { clearScreen: false }
};
