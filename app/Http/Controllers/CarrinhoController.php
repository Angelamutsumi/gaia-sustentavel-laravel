<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Pedido;
use App\Produto;
use App\PedidoProduto;
use App\CupomDesconto;

class CarrinhoController extends Controller
{
    function __construct() // Só para usuarios logados.
    {
        $this->middleware('auth');
    }
    
    function index() {

        $pedidos = Pedido::where([
            'status' => 'RE',
            'user_id' => Auth::id()
        ])->get();

        return view('/carrinho', compact('pedidos'));
    }

    function add() {
        $this->middleware('VerifyCsrToken');

        $req = Request();
        $idProduto = $req->input('id');

        $produto = Produto::find($idProduto);
        if(empty($produto->id)) {
            $req->session()->flash('mensagem falha','Produto não encontrado na nossa loja');
            return redirect()->route('carrinho.index');  // inserir a rota da página de busca de produto.
        }

        $idUsuario = Auth::id();

        $idPedido = Pedido::consultaId([  // Para verificar se o usuário possui um pedido em aberto, se sim ele é reutilizado. Se não, é gerao um novo.
            'user_id' => $idUsurario,
            'status' => 'RE'    //produto reservado
        ]);

        if(empty($idPedido)) {
            $pedido_novo = Pedido::create([
                'user_id' => $idUsurario,
                'status' => 'RE'    //produto reservado
            ]);

            $idPedido = $pedido_novo->id;
        }

        PedidoProduto::create([ 
            'request_id' => $idPedido,
            'product_id' => $idProduto,
            'price' => $produto->price,
            'status' => 'RE'
        ]);
        $req->session()->flash('mensagem sucesso', 'Produto adicionado ao carrinho com sucesso!');
        return redirect()->route('carrinho.index');   // Para verificar se o usuário possui um pedido em aberto, se sim ele é reutilizado. Se não, é gerao um novo.
        }


        function delete() {
            $this->middleware('VerifyCsrToken');

            $req = Request();
            $idPedido = $req->input('request_id');
            $idProduto = $req->input('product_id');
            $remove_apenas_item = (boolean)$req->input('item'); // true se remove só um item e false para todos os produtos.
            $idUsuário = Auth::id();

            $idPedido = Pedido::consultaId([  // Verifica se o pedido é do usuário logado e se está em status adequado.
                'id' => $idPedido,
                'user_id' => $idUsuario,
                'status' => 'RE' // Reservado
            ]);
            if(empty($idPedido)) {
                $req->session()->flash('mensagem-falha', 'Pedido não encontrado!');
                return redirect()->route('carrinho.index'); 
            }

            $where_produto = [
                'request_id' => $idPedido,
                'product_id' => $idProduto
            ];

            $produto = PedidoProduto::where($where_produto)->orderBy('id', 'desc')->first(); // 'desc' é do > para o <
            if(empty($produto->id)) {
                $req->session()->flash('mensagem-falha', 'Produto não encontrado no carrinho!');
                return redirect()->route('carrinho.index');
            }

            if($remove_apenas_item) {
                $where_produto['id'] = $produto->id; // Remove o último produto adicionado.
            }
            PedidoProduto::where($where_produto)->delete(); // Remove todos os produtos.

            $check_pedido = PedidoProduto::where([    // Verifica se há algum outro produto vinculado a este pedido.
                'request_id' => $produto->pedido_id
                ])->exists();

            if(!$check_pedido) {   // Se o pedido estiver vazio, apaga o pedido.
                Pedido::where([
                    'id' => $produto->pedido_id
                ])->delete();
            }

            $req->session()->flash('mensagem-sucesso', 'Produto removido do carrinho com sucesso!');
                return redirect()->route('carrinho.index'); // Verificar se vai para esta página mesmo.
                
        }

        public function complete() {
            $this->middleware('VerifyCsrToken');

            $req = Request();
            $idPedido = $req->input('request_id');
            $idUsuário = Auth::id();

            $check_pedido = PedidoProduto::where([    // Verifica se há algum outro produto vinculado a este pedido.
                'id' => $idPedido,
                'user_id' => $idUsuario,
                'status' => 'RE'
                ])->exists();

            if(!check_pedido) {
                $req->session()->flash('mensagem-falha', 'Pedido não encontrado!');
                return redirect()->route('carrinho.index');
            } 

            $check_produto = PedidoProduto::where([
                'request_id' => $idPedido
                ])->exists();

            if(!$check_produtos) {
                $req->session()->flash('mensagem-falha', 'Produtos do pedido não encontrados!');
                return redirect()->route('carrinho.index');
            }

            PedidoProduto::where([
                'request_id' => $idPedido
            ])->update([
                'status' => 'PA'
            ]);

            Pedido::where([
                'id' => $idPedido
            ])->update([
                'status' => 'PA'
            ]);

            $req->session()->flash('mensagem-sucesso', 'Compra concluída com sucesso!!');

            return redirect()->route('carrinho.compras');
        } 

        public function purchase() {
            $compras = Pedido::where([
                'status' => 'PA',
                'user_id' => Auth::id()
                ])->orderBy('created_at', 'desc')->get();
            
            return $compras;

            $cancelados = Pedido::where([
                'status' => 'PA',
                'user_id' => Auth::id()
                ])->orderBy('updated_at', 'desc')->get();
            
            return view('compras', compact('compras', 'cancelados'));
        }


        public function cancel() {
            $this->middleware('VerifyCsrToken');

            $req = Request();
            $idPedido = $req->input('request_id');
            $idspedido_produto-> $req->input('id');
            $idUsuário = Auth::id();

            if(empty($idspedido_produto)) {
                $req->session()->flash('mensagem-falha', 'Nenhum item selecionado');
                return redirect()->route('carrinho.compras');
            }

            $check_pedido = Pedido::where([
                'id' => $idPedido,
                'user_id' => $idUsuario,
                'status' => 'PA'
                ])->exists();

            if(!check_pedido) {
                $req->session()->flash('mensagem-falha', 'Pedido não encontrado para cancelamento!');
                return redirect()->route('carrinho.compras');
            } 

            $check_produto = PedidoProduto::where([
                'request_id' => $idPedido,
                'status' => 'PA'
                ])->whereIn('id', $idspedido_produto)->exists();

            if(!$check_produtos) {
                $req->session()->flash('mensagem-falha', 'Produtos do pedido não encontrados!');
                return redirect()->route('carrinho.compras');
            }

            PedidoProduto::where([
                'request_id' => $idPedido,
                'status' => 'PA',
            ])->whereIn('id',$idspedido_produto)->update([
                'status' => 'CA'
            ]);

            $check_pedido_cancel = PedidoProduto::where([   // Verifica se ainda há no pedido algum produto com status pago.
                'request_id' => $idPedido,
                'status' => 'PA'
            ])->exists();

            if(!check_pedido_cancel) {  // Se não sobrou nenhum produto, é cancelado o pedido.
                Pedido::where([
                    'id' => $idPedido
                ])->update([
                    'status' => 'CA'
                ]);

                $req->session()->flash('mensagem-sucesso', 'Compra cancelada com sucesso!');
            } else {
                $req->session()->flash('mensagem-sucesso', 'Item(s) da compra cancelados com sucesso!');
            }

            return redirect()->route('carrinho.compras');


        }

        public function discount() {
            $this->middleware('VerifyCsrToken');

            $req = Request();
            $idPedido = $req->input('request_id');
            $cupom-> $req->input('cupom');
            $idUsuário = Auth::id();

            if(empty($cupom)) {
                $req->session()->flash('mensagem-falha', 'Cupom inválido');
                return redirect()->route('carrinho.index');
            }

            $cupom = CupomDesconto::where([
                'localizador' => $cupom,
                'ativo' => 'S',
                ])->where('validade', '>', date('Y-m-d H:i:s'))->first();

            if(empty($cupom->id)) {
                $req->session()->flash('mensagem-falha', 'Cupom de desconto não encontrado');
                return redirect()->route('carrinho.index');
            } 

            $check_pedido = Pedido::where([
                'id' => $idPedido,
                'user_id' => $idUsuario,
                'status' => 'RE'
                ])->exists();

            if(!$check_pedido) {
                $req->session()->flash('mensagem-falha', 'Pedido não encontrado para validação!');
                return redirect()->route('carrinho.index');
            }

            $pedido_produtos = PedidoProduto::where([
                'request_id' => $idPedido,
                'status' => 'RE',
            ])->get();

           if(empty($pedido_produtos)) {
               $req->session()->flash('mensagem-falha', 'Produtos do pedido não encontrados!');
               return redirect()->route('carrinho.index');
           }

           $aplicouDesconto = false;
           foreach($pedido_produtos as $pedido_produto) {
               switch($cupom->type_discount) {
                   case 'porc':
                    $valor_desconto = ($pedido_produto->price = $$cupom->discount) /100;
                   break;
                   default:
                    $valor_desconto = $cupom->discount;
                   break;
               }
           }

           $valor_desconto = ($valor_desconto > $pedido_produto->price) ? $pedido_produto->price : number_format($valor_desconto, 2);

           switch ($cupom -> type_limit) {
               case 'qtd':
                $qtd_pedido = PedidoProduto::whereIn('status', ['PA', 'RE'])->where([
                    'discount_coupon_id' => $cupom->id,
                    ])->count();
                if($qtd_pedido >= $cupom->limit) {
                    continue;
                }
                break;

            default:
            $valor_ckc_descontos = PedidoProduto::whereIn('status', ['PA', 'RE'])->where([
                'discount_coupon_id' -> $cupom->id
            ])->sum('discount');

            if(($valor_ckc_descontos+$valor_desconto)-> $cupom->limit) {
                continue;
            }
            break;
            }

            $pedido_produto->discount_coupon_id = $cupom->id;
            $pedido_produto->discount = $valor_desconto;
            $pedido_produto->update();

            $aplicouDesconto = true;

        
            if($aplicouDesconto) {
                $req->session()->flash('mensagem-sucesso', 'Cupom aplicado com sucesso!');
            } else {
                $req->session()->flash('mensagem-falha', 'Cupom esgotado!');
            }
            return redirect()->route('carrinho.index');
    }
}