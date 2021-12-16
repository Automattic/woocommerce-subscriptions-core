module.exports = {
	'*.{js,jsx,ts,tsx}': [ 'npm run format:js', 'npm run lint:js' ],
	'*.{ts,tsx}': [ () => 'tsc --noEmit' ],
	'*.{scss,css}': [ 'npm run lint:css' ],
	'*.php': 'bin/phpcs.sh',
	'composer.json': 'composer validate --strict --no-check-all',
};
