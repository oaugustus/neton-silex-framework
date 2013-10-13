<?php

namespace Neton\Silex\Framework;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Silex\Application;

class BaseStore extends BaseService
{
    /**
     * Tabela origem do store.
     *
     * @var string
     */
    protected $table;

    /**
     * Campo utilizado como chave da tabela.
     *
     * @var string
     */
    protected $id = 'id';

    /**
     * Nome da conexão default.
     *
     * @var string
     */
    protected $connectionName = 'default';

    /**
     * @var Connection;
     */
    protected $qb;

    /**
     * @var
     */
    protected $db;

    /**
     * Inicializa o Store.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->db = $this->getConnection();
        $this->qb = $this->db->createQueryBuilder();
    }

    /**
     * Localiza um registro pelo seu id.
     *
     * @param Integer $id
     *
     * @return Array
     */
    public function find($id)
    {
        $qb = $this->qb;

        $qb->select('t.*')
           ->from($this->table, 't')
           ->where($this->id." = :id");

        return $this->db->fetchAssoc($qb->getSQL(), array(
            'id' => $id
        ));
    }

    /**
     * Localiza o primeiro registro que casa com um conjunto de filtros.
     *
     * @param Array $filters
     *
     * @return Array
     */
    public function findOneBy($filters)
    {
        $qb = $this->qb;

        $qb->select('t.*')
           ->from($this->table, 't')
           ->where('1 = 1');

        foreach ($filters as $key => $filter) {
            $qb->andWhere('t.'.$key.' = :'.$key);
        }

        return $this->db->fetchAssoc($qb->getSQL(), $filters);
    }

    /**
     * Localiza os registros que casam com um conjunto de filtros.
     *
     * @param Array $filters
     * @param Array $opt
     *
     * @return Array
     */
    public function findBy($filters, $opt = array())
    {
        $qb = $this->qb;

        $qb->select('t.*')
            ->from($this->table, 't')
            ->where('1 = 1');

        foreach ($filters as $key => $filter) {
            $qb->andWhere('t.'.$key.' = :'.$key);
        }

        return $this->paginate($qb, $opt, $filters);
    }

    /**
     * Salva um registro no banco de dados.
     *
     * @param Array $data
     *
     * @return Mixed
     */
    public function save($data)
    {
        $id = false;

        if ($data[$this->id] == 0){
            unset($data[$this->id]);

            try {
                $this->db->insert($this->table, $this->fromArray($data));

                $id = $this->db->lastInsertId($this->id);
            } catch(\Exception $e){
            }

        } else {
            try {
                $this->db->update($this->table, $this->fromArray($data), array(
                    $this->id => $data[$this->id]
                ));

                $id = $data[$this->id];

            } catch(\Exception $e){
            }
        }

        return $id;
    }

    /**
     * Exclui um registro da tabela.
     *
     * @param Integer $id
     *
     * @return Boolean
     */
    public function remove($id)
    {
        try {
            $this->db->delete($this->table, array(
                $this->id => $id
            ));

            return true;
        } catch(\Exception $e){

            return false;
        }
    }

    /**
     * Pagina o resultado de uma consulta.
     *
     * @param QueryBuilder $qb
     * @param Array $opt
     * @param Array $filters
     *
     * @return Array
     */
    protected function paginate(QueryBuilder $qb, $opt, $filters = array())
    {
        $db = $this->db;

        $total = count($db->fetchAll($qb->getSQL(), $filters));

        $qb->setFirstResult(isset($opt['start']) ? $opt['start'] : 0)->
            setMaxResults(isset($opt['limit']) ? $opt['limit'] : 200);

        $result = $db->fetchAll($qb->getSQL(), $filters);

        return array(
            'total' => $total,
            'results' => $result
        );
    }

    /**
     * Mapeia os dados recebidos de um array nos campos existentes na tabela.
     *
     * @param Array $source
     *
     * @return Array
     */
    protected function fromArray($source)
    {
        $data = array();
        $columnNames = $this->getColumnNames();

        foreach ($source as $key => $value) {

            if (in_array($key, $columnNames)) {

                if (is_string($value)){
                    $value = ($value);
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Retorna os nomes das colunas da tabela.
     *
     * @return Array
     *
     * @return Array
     */
    private function getColumnNames()
    {
        $names = array();
        $columns = $this->db->getSchemaManager()->listTableColumns($this->table);

        foreach ($columns as $column){
            $names[] = $column->getName();
        }

        return $names;
    }

    /**
     * Recupera a conexão utilizada pelo store.
     *
     * @return Connection
     */
    private function getConnection()
    {
        if ('default' == $this->connectionName){
            return $this->app['db'];
        } else {
            return $this->app['dbs'][$this->connectionName];
        }
    }
}