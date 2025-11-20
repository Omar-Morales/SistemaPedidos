import { defineConfig } from 'vite';
import path from 'path';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/categoria.js',
                'resources/js/customer.js',
                'resources/js/supplier.js',
                'resources/js/user.js',
                'resources/js/profile.js',
                'resources/js/product.js',
                'resources/js/compra.js',
                'resources/js/venta.js',
                'resources/js/inventorie.js',
                'resources/js/rol.js',
            ],
            refresh: true,
        }),
    ],
optimizeDeps: {
    include: [
      'jquery',
      'datatables.net',
      'datatables.net-bs5',
      'datatables.net-buttons',
      'datatables.net-buttons-bs5',
      'datatables.net-buttons/js/buttons.colVis.js',
      'select2',
      'sweetalert2'
    ],
  },
resolve: {
    alias: {
            jquery: path.resolve(__dirname, 'node_modules/jquery'),
            'datatables.net': path.resolve(__dirname, 'node_modules/datatables.net/js/jquery.dataTables.js'),
            'datatables.net-bs5': path.resolve(__dirname, 'node_modules/datatables.net-bs5/js/dataTables.bootstrap5.js'),
            'datatables.net-buttons': path.resolve(__dirname, 'node_modules/datatables.net-buttons'),
            'datatables.net-buttons-bs5': path.resolve(__dirname, 'node_modules/datatables.net-buttons-bs5/js/buttons.bootstrap5.js'),
            sweetalert2: path.resolve(__dirname, 'node_modules/sweetalert2')
    },
},

});
