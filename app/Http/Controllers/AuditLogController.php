<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index()
    {
        return view('audit-logs.index');
    }

    public function list(Request $request)
    {
        $offset    = (int) $request->input('offset', 0);
        $limit     = (int) $request->input('limit', 10);
        $sort      = $request->input('sort', 'id');
        $order     = $request->input('order', 'DESC');
        $search    = $request->input('search');
        $entityType = $request->input('entity_type');
        $actorType  = $request->input('actor_type');
        $action     = $request->input('action');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $source     = $request->input('source');

        $sql = AuditLog::query();

        if ($entityType) {
            $sql->where('entity_type', $entityType);
        }
        if ($actorType) {
            $sql->where('actor_type', $actorType);
        }
        if ($action) {
            $sql->where('action', $action);
        }
        if ($source) {
            $sql->where('source', $source);
        }
        if ($dateFrom) {
            $sql->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $sql->whereDate('created_at', '<=', $dateTo);
        }
        if ($search) {
            $sql->where(function ($q) use ($search) {
                $q->where('actor_name', 'LIKE', "%{$search}%")
                    ->orWhere('entity_title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $total = $sql->count();
        $rows  = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $result = $rows->map(function ($row) {
            return [
                'id'           => $row->id,
                'actor_type'   => ucfirst($row->actor_type),
                'actor_name'   => $row->actor_name,
                'source'       => $row->source === 'admin_panel' ? 'Admin Panel' : 'App / Web',
                'entity_type'  => ucfirst($row->entity_type),
                'entity_id'    => $row->entity_id,
                'entity_title' => $row->entity_title ?? '—',
                'action'       => ucfirst(str_replace('_', ' ', $row->action)),
                'description'  => $row->description,
                'created_at'   => $row->created_at ? date('d M Y, h:i A', strtotime($row->created_at)) : '—',
            ];
        });

        return response()->json(['total' => $total, 'rows' => $result]);
    }
}
