( function( global ) {
    global.CKEDITOR = global.CKEDITOR || {};
    global.CKEDITOR.plugins = global.CKEDITOR.plugins || {};

    global.CKEDITOR.plugins.add( 'autolink', {
        init: function( editor ) {
            // Daftarkan perintah autolink
            editor.addCommand( 'autolink', {
                exec: function( editor ) {
                    const selection = editor.getSelection();
                    const selectedText = selection.getSelectedText();

                    if ( selectedText ) {
                        // Bungkus teks dengan [[...]]
                        editor.insertText( `[[${selectedText}]]` );
                        // Hapus teks asli
                        selection.removeAllRanges();
                    }
                }
            } );

            // Tambahkan tombol ke toolbar
            editor.ui.addButton( 'Autolink', {
                label: 'Buat Autolink',
                command: 'autolink',
                toolbar: 'insert',
                icon: 'data:image/svg+xml;utf8,<svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="4" width="3" height="8" fill="none" stroke="%23000" stroke-width="1.5"/><rect x="11" y="4" width="3" height="8" fill="none" stroke="%23000" stroke-width="1.5"/><path d="M5 8h6" stroke="%23000" stroke-width="1.5"/></svg>'
            } );
        }
    } );
} )( window );