<?php

namespace App\Services;

class BootstrapTableService
{
    private static string $defaultClasses = 'btn btn-xs btn-rounded btn-icon m-1';

    /**
     * @return string
     */
    public static function button(string $iconClass, string $url, array $customClass = [], array $customAttributes = [], string $iconText = '')
    {
        $customClassStr = implode(' ', $customClass);
        $class = self::$defaultClasses.' '.$customClassStr;
        $attributes = '';
        if (count($customAttributes) > 0) {
            foreach ($customAttributes as $key => $value) {
                $attributes .= $key.'="'.$value.'" ';
            }
        }
        if (! empty($iconText)) {
            $iconTextElement = '<span class="icon_text">'.$iconText.'</span>';
        } else {
            $iconTextElement = '';
        }

        $href = '';
        if (! empty($url) && $url !== '#') {
            $href = 'href="'.$url.'"';
        }

        return '<a '.$href.' class="'.$class.'" '.$attributes.'><i class="'.$iconClass.'"></i>'.$iconTextElement.'</a>';
    }

    /**
     * @return string
     */
    public static function editButton($url, bool $modal = false, $dataBsTarget = null, $customClass = null, $id = null, $onClick = null, $data_types = '', $iconClass = null, $iconText = '')
    {
        $customClass = ['btn icon btn-primary btn-sm rounded-pill border border-success edit_btn '.$customClass];
        $customAttributes = [
            'title' => trans('Edit'),
        ];
        if ($modal) {
            $customAttributes = [
                'title' => 'Edit',
                'data-toggle' => 'modal',
                'data-bs-target' => ! isset($dataBsTarget) ? '#editModal' : $dataBsTarget,
                'data-bs-toggle' => 'modal',
                'id' => $id,
                'onclick' => $onClick,
                'data-types' => $data_types,
            ];
        }
        $iconClass = isset($iconClass) ? $iconClass : 'fa fa-edit edit_icon';

        return self::button($iconClass, $url, $customClass, $customAttributes, $iconText);
    }

    /**
     * @return string
     */
    public static function deleteButton($url, $id = null, $dataId = null, $dataCategory = null, $onclick = true, $customClass = null)
    {
        $customClass = ['btn icon btn-danger delete_btn border border-danger btn-sm rounded-pill '.$customClass];
        $customAttributes = [
            'title' => trans('Delete'),
            'onclick' => $onclick ? 'return confirmationDelete(event);' : '',
            'id' => $id,
            'data-id' => $dataId,
            'data-category' => $dataCategory,

        ];
        $iconClass = 'fa fa-trash delete_icon';

        return self::button($iconClass, $url, $customClass, $customAttributes);
    }

    /**
     * @return string
     */
    public static function restoreButton($url, string $title = 'Restore')
    {
        $customClass = ['btn-gradient-success', 'restore-data'];
        $customAttributes = [
            'title' => trans($title),
        ];
        $iconClass = 'fa fa-refresh';

        return self::button($iconClass, $url, $customClass, $customAttributes);
    }

    /**
     * @return string
     */
    public static function trashButton($url)
    {
        $customClass = ['btn-gradient-danger', 'trash-data'];
        $customAttributes = [
            'title' => trans('Delete Permanent'),
        ];
        $iconClass = 'fa fa-times';

        return self::button($iconClass, $url, $customClass, $customAttributes);
    }

    /**
     * @return string
     */
    public static function viewRelatedDataButton($url)
    {
        $customClass = ['related-data-form', 'btn-inverse-primary'];
        $customAttributes = [
            'title' => trans('View Related Data'),

        ];
        $iconClass = 'fa fa-eye';

        return self::button($iconClass, $url, $customClass, $customAttributes);
    }

    public static function optionButton($url)
    {
        $customClass = ['btn-option'];
        $customAttributes = [
            'title' => trans('View Option Data'),
        ];
        $iconClass = 'bi bi-gear';
        $iconText = ' Options';

        return self::button($iconClass, $url, $customClass, $customAttributes, $iconText);
    }

    public static function deleteAjaxButton($url)
    {
        $customClass = ['delete-form', 'btn icon btn-danger border border-danger delete_btn btn-sm rounded-pill'];
        $customAttributes = [
            'title' => trans('Delete'),
        ];
        $iconClass = 'fa fa-trash delete_icon';

        return self::button($iconClass, $url, $customClass, $customAttributes);
    }

    public static function dropdown(
        string $iconClass,
        array $dropdownItems,
        array $customClass = [],
        array $customAttributes = []
    ) {
        $customClassStr = implode(' ', $customClass);
        $class = self::$defaultClasses.' dropdown '.$customClassStr;
        $attributes = '';

        if (count($customAttributes) > 0) {
            foreach ($customAttributes as $key => $value) {
                $attributes .= $key.'="'.$value.'" ';
            }
        }

        $dropdown = '<div class="'.$class.'" '.$attributes.'>';
        $dropdown .= '<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">';
        $dropdown .= '<i class="'.$iconClass.'"></i>';
        $dropdown .= '</button>';
        $dropdown .= '<ul class="dropdown-menu" data-bs-popper="static" aria-labelledby="dropdownMenuButton">';

        foreach ($dropdownItems as $item) {
            $dropdown .= '<li><a class="dropdown-item" href="'.$item['url'].'"><i class="'.$item['icon'].'"></i> '.$item['text'].'</a></li>';
        }

        $dropdown .= '</ul>';
        $dropdown .= '</div>';

        return $dropdown;
    }
}
