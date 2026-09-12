<?php

namespace App\Support;

use App\Models\Order;

class SalesReceiptPdf
{
    /**
     * @return non-empty-string
     */
    public function render(Order $order): string
    {
        $order->loadMissing('shop', 'items');

        $lines = $this->receiptLines($order);
        $content = "BT\n/F1 18 Tf\n50 790 Td\n".$this->text($order->shop->name)."\n";
        $content .= "/F1 11 Tf\n0 -24 Td\n".$this->text('Sales receipt')."\n";
        foreach ($lines as $line) {
            $content .= '0 -16 Td '.$this->text($line)."\n";
        }
        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];

        return $this->compile($objects);
    }

    /**
     * @return list<string>
     */
    private function receiptLines(Order $order): array
    {
        $lines = [
            'Receipt no: '.($order->receipt_number ?: $order->reference),
            'Reference: '.$order->reference,
            'Status: '.str_replace('_', ' ', ucfirst($order->status)),
            'Customer: '.$order->customer_name,
        ];

        if ($order->customer_phone) {
            $lines[] = 'Phone: '.$order->customer_phone;
        }

        if ($order->customer_email) {
            $lines[] = 'Email: '.$order->customer_email;
        }

        $lines[] = 'Date: '.$order->created_at->format('d M Y, H:i');
        $lines[] = 'Payment method: '.ucfirst($order->payment_method ?? $order->channel);
        $lines[] = '';
        $lines[] = 'Items';

        foreach ($order->items as $item) {
            $lines[] = $item->quantity.' x '.$item->name.' - '.$order->currency.' '.number_format($item->quantity * $item->unit_price / 100, 2);
        }

        if ($order->discount_total > 0) {
            $lines[] = '';
            $lines[] = 'Subtotal: '.$order->currency.' '.number_format($order->subtotal / 100, 2);
            $lines[] = 'Discount'.($order->discount_name ? ' - '.$order->discount_name : '').': -'.$order->currency.' '.number_format($order->discount_total / 100, 2);
        }

        $lines[] = '';
        $lines[] = 'Total: '.$order->currency.' '.number_format($order->total / 100, 2);
        $lines[] = '';
        $lines[] = 'Thank you for shopping with '.$order->shop->name.'.';

        return $lines;
    }

    private function text(string $value): string
    {
        $safeValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $safeValue = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', ' ', ' '], $safeValue);

        return '('.$safeValue.') Tj';
    }

    /**
     * @param  list<string>  $objects
     * @return non-empty-string
     */
    private function compile(array $objects): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT).' 00000 '.($offset === 0 ? 'f' : 'n')." \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }
}
