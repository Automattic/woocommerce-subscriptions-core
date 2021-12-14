module.exports = {
	'*.{js,jsx,ts,tsx}': [ 'npm run lint:js', 'npm run format:js' ],
	'*.{ts,tsx}': [ () => 'tsc --noEmit' ],
	'*.{scss,css}': [ 'npm run lint:css' ],
	'*.php': 'bin/phpcs.sh',
	'composer.json': 'composer validate --strict --no-check-all',
};
