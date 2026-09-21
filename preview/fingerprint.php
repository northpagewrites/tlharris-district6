<?php
/**
 * Fingerprint of the deployable tree. Needs no WordPress.
 *
 * WordPress Playground builds a preview by checking this repository out from
 * GitHub. This reduces what it checked out to one hash, so a reviewer can prove
 * the preview is the commit they meant. `node scripts/preview.mjs fingerprint
 * <commit>` computes the same value straight from Git, with no checkout.
 *
 * The rule is plain on purpose, so both implementations agree exactly:
 *   1. Take every regular file under the paths below, named relative to the
 *      repository root with forward slashes.
 *   2. Sort the names byte-wise.
 *   3. Make one line per file: sha256(file contents), two spaces, the name,
 *      a newline.
 *   4. The fingerprint is sha256 of all those lines joined.
 * `.DS_Store` and `Thumbs.db` are ignored.
 *
 * If you change the list of paths, change PATHS in scripts/preview.mjs too.
 * scripts/validate.sh compares the two implementations and fails if they differ.
 */

if ( ! function_exists( 'tlharris_preview_fingerprint' ) ) {

	/**
	 * @param string $root Repository root, with no trailing slash.
	 * @return array{fingerprint:string,files:int}
	 */
	function tlharris_preview_fingerprint( $root ) {
		$paths = array(
			'wp-content/themes/tlharris-public',
			'wp-content/plugins/tlharris-core',
			'content',
			'preview',
		);
		$files = array();

		foreach ( $paths as $relative ) {
			$dir = $root . '/' . $relative;

			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || in_array( $file->getFilename(), array( '.DS_Store', 'Thumbs.db' ), true ) ) {
					continue;
				}

				$name           = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
				$files[ $name ] = $file->getPathname();
			}
		}

		ksort( $files, SORT_STRING );

		$listing = '';
		foreach ( $files as $name => $absolute ) {
			$listing .= hash_file( 'sha256', $absolute ) . '  ' . $name . "\n";
		}

		return array(
			'fingerprint' => hash( 'sha256', $listing ),
			'files'       => count( $files ),
		);
	}
}
