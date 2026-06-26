/**
 * src/components/SettingsCard.js
 *
 * Card > CardHeader > CardBody with a two-column fixed-layout table.
 * Directly ported from COS Pro SettingsCard — column widths and DOM
 * structure are identical so the same SCSS applies.
 *
 * Props:
 *   heading     {string}   Card heading text (required)
 *   subHeading  {string}   Optional subtitle below heading
 *   fields      {Array}    Array of field descriptor objects (see below)
 *   display     {bool}     Set false to hide the whole card
 *   control     {object}   react-hook-form control instance
 *   className   {string}   Extra CSS class on the Card root
 *
 * Field descriptor shape:
 *   {
 *     name        : string,                           // RHF field name
 *     label       : string|null,                      // left-column label (null → field spans both cols)
 *     render      : (field, error) => ReactNode,      // right-column renderer
 *     defaultValue: any,                              // RHF default value
 *     rules       : object,                           // RHF validation rules
 *     showWhen    : bool,                             // if false the row is hidden (default: true)
 *   }
 */

import {
    Card,
    CardHeader,
    CardBody,
    __experimentalVStack as VStack,
    __experimentalHeading as Heading,
    __experimentalText as Text,
} from '@wordpress/components';
import { Controller } from 'react-hook-form';

const SettingsCard = ( {
    heading,
    subHeading = null,
    fields     = [],
    display    = true,
    control,
    className  = '',
} ) => {
    if ( ! display ) return null;

    return (
        <Card className={ className }>
            <CardHeader>
                <VStack spacing={ 2 }>
                    <Heading level={ 4 }>{ heading }</Heading>
                    { subHeading && (
                        <Text className="components-text">{ subHeading }</Text>
                    ) }
                </VStack>
            </CardHeader>

            <CardBody>
                <table className="pgbf-settings-table">
                    <colgroup>
                        <col className="pgbf-settings-table__label-col" />
                        <col className="pgbf-settings-table__field-col" />
                    </colgroup>
                    <tbody>
                        { fields.map( ( field, index ) =>
                            ( field.showWhen === undefined || field.showWhen ) ? (
                                <tr key={ index } className="pgbf-settings-table__row">
                                    { field.label ? (
                                        <td className="pgbf-settings-table__label">
                                            <Text className="pgbf-settings-label">
                                                { field.label }
                                            </Text>
                                        </td>
                                    ) : null }
                                    <td
                                        className="pgbf-settings-table__field"
                                        colSpan={ field.label ? 1 : 2 }
                                    >
                                        <Controller
                                            name={ field.name }
                                            control={ control }
                                            defaultValue={ field.defaultValue }
                                            rules={ field.rules }
                                            render={ ( { field: controllerField, fieldState: { error } } ) =>
                                                field.render( controllerField, error )
                                            }
                                        />
                                    </td>
                                </tr>
                            ) : null
                        ) }
                    </tbody>
                </table>
            </CardBody>
        </Card>
    );
};

export default SettingsCard;
