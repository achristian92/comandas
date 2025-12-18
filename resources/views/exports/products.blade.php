<table>
    <thead>
    <tr>
        <th>PRODUCTO</th>
        <th>SKU</th>
        <th>UPC</th>
        <th>STOCK</th>
        <th>MIN STOCK</th>
        <th>SUCURSAL</th>
        <th>LOCAL</th>
        <th>COD PEDIDOSYA</th>
    </tr>
    </thead>
    <tbody>
    @foreach($stocks as $data)
        <tr>
            <td>{{ $data->product->name }}</td>
            <td>{{ $data->product->sku }}</td>
            <td>{{ $data->product->upc }}</td>
            <td>{{ $data->stock }}</td>
            <td>{{ $data->min_stock }}</td>
            <td>{{ $data->branch->name }}</td>
            <td>{{ $data->branch->location }} - {{ $data->branch->address }}</td>
            <td>{{ $data->branch->pedidos_ya_code }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
